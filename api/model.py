import os
import re
import warnings
import numpy as np
import pandas as pd
import matplotlib.pyplot as plt
import seaborn as sns
import emoji
from collections import Counter
import joblib

warnings.filterwarnings('ignore')
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'
os.environ['CUDA_VISIBLE_DEVICES'] = '-1'

from sklearn.preprocessing import MinMaxScaler, OneHotEncoder
from sklearn.model_selection import (
    train_test_split, StratifiedKFold, RandomizedSearchCV, cross_val_score
)
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.neural_network import MLPClassifier
from sklearn.metrics import (
    accuracy_score, precision_score, recall_score,
    f1_score, classification_report, confusion_matrix, roc_auc_score
)
from imblearn.over_sampling import SMOTE

# ============================================================
# GLOBAL CONSTANTS
# ============================================================

RANDOM_STATE = 42
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
EXCEL_PATH = os.path.join(BASE_DIR, 'Tourists-Records_10000.xlsx')
CSV_PATH = os.path.join(BASE_DIR, 'tourist_insights_processed.csv')
RANKING_PATH = os.path.join(BASE_DIR, 'location_ranking.csv')
MODEL_PATH = os.path.join(BASE_DIR, 'trained_model.joblib')

# ============================================================
# OFFLINE TEXT UTILITIES
# ============================================================

STOPWORDS_ENGLISH = {
    'i', 'me', 'my', 'myself', 'we', 'our', 'ours', 'ourselves', 'you', 'your', 'yours',
    'yourself', 'yourselves', 'he', 'him', 'his', 'himself', 'she', 'her', 'hers',
    'herself', 'it', 'its', 'itself', 'they', 'them', 'their', 'theirs', 'themselves',
    'what', 'which', 'who', 'whom', 'this', 'that', 'these', 'those', 'am', 'is', 'are',
    'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'having', 'do', 'does',
    'did', 'doing', 'a', 'an', 'the', 'and', 'but', 'if', 'or', 'because', 'as', 'until',
    'while', 'of', 'at', 'by', 'for', 'with', 'about', 'against', 'between', 'into',
    'through', 'during', 'before', 'after', 'above', 'below', 'to', 'from', 'up', 'down',
    'in', 'out', 'on', 'off', 'over', 'under', 'again', 'further', 'then', 'once', 'here',
    'there', 'when', 'where', 'why', 'how', 'all', 'any', 'both', 'each', 'few', 'more',
    'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so',
    'than', 'too', 'very', 's', 't', 'can', 'will', 'just', 'don', 'should', 'now'
}


def text_preprocessing(text):
    """Lowercase, emoji → text, strip URLs and special characters."""
    if pd.isna(text):
        return ''
    text = emoji.demojize(str(text).lower())
    text = re.sub(r'https?://\S+|www\.\S+', '', text)
    text = re.sub(r'[^a-zA-Z0-9\s]', ' ', text)
    return re.sub(r'\s+', ' ', text).strip()


def remove_stopwords(text):
    words = re.findall(r'\b\w+\b', str(text).lower())
    return ' '.join(w for w in words if w not in STOPWORDS_ENGLISH and len(w) > 1)


def map_satisfaction(label):
    if pd.isna(label):
        return np.nan
    s = str(label).strip().lower()
    if s.startswith('satisfied') and not s.startswith('unsatisfied'):
        return 1
    if s.startswith('unsatisfied'):
        return 2
    return np.nan

# ============================================================
# STAGE 1 — EXCEL → CSV  (text-only preprocessing)
# ============================================================

def load_and_preprocess_excel(file_path):
    """
    Load raw Excel, clean, and return a DataFrame.

    What happens here:
    - De-duplicate rows
    - Drop rows where BOTH Feedbacks and Ratings are missing
    - Remove ambiguous neutral Ratings (==3)
    - Text clean + stopword removal → Processed_Feedback
    - Satisfaction label mapping
    - Has_Feedback flag (1 if text ≥ 3 words, else 0)

    What does NOT happen here:
    - No TF-IDF fitting
    - No scaling
    - No encoding
    - No Sentiment_Score  ← removed (redundant with TF-IDF)
    """
    df = pd.read_excel(file_path)
    df = df.drop_duplicates()

    # Require at least one of: text OR rating
    df = df[~(df['Feedbacks'].isna() & df['Ratings'].isna())].copy()

    # Remove neutral/ambiguous rating
    df = df[df['Ratings'] != 3].copy()

    # Text preprocessing
    df['Processed_Feedback'] = (
        df['Feedbacks']
        .apply(text_preprocessing)
        .apply(remove_stopwords)
    )

    # Keep row if text is long enough OR a valid rating exists as fallback
    word_counts    = df['Processed_Feedback'].str.split().str.len().fillna(0)
    has_short_text = word_counts < 3
    has_rating     = df['Ratings'].notna()
    df = df[~has_short_text | has_rating].reset_index(drop=True)

    # Target label
    df['Satisfaction_Label'] = df['Satisfaction_Label'].apply(map_satisfaction)
    df = df.dropna(subset=['Satisfaction_Label']).reset_index(drop=True)

    # Binary flag: does this row have usable text?
    df['Has_Feedback'] = (
        df['Processed_Feedback'].str.split().str.len().fillna(0) >= 3
    ).astype(int)

    return df


def build_csv(excel_path, csv_path):
    df = load_and_preprocess_excel(excel_path)
    df.to_csv(csv_path, index=False)
    return df

# ============================================================
# STAGE 2 — RELOAD CSV
# ============================================================

def reload_csv(csv_path):
    df = pd.read_csv(csv_path)
    df['Processed_Feedback'] = df['Processed_Feedback'].fillna('').astype(str)
    df['Ratings']            = pd.to_numeric(df['Ratings'], errors='coerce')
    df['Has_Feedback']       = df['Has_Feedback'].fillna(0).astype(int)
    df['Location']           = df['Location'].fillna('Unknown').astype(str)
    return df

# ============================================================
# STAGE 3 — STRATIFIED SPLIT  70 / 15 / 15  (index-based)
# ============================================================

def split_data(df):
    """
    Returns six objects: df_train, y_train, df_val, y_val, df_test, y_test.
    Splitting is done on row indices so NO transformers are touched yet.
    """
    y   = (df['Satisfaction_Label'] == 1).astype(int)
    idx = np.arange(len(df))

    # 85% temp  +  15% test
    idx_temp, idx_test, y_temp, y_test = train_test_split(
        idx, y, test_size=0.15, stratify=y, random_state=RANDOM_STATE
    )

    # 70% train  +  15% val  (15/85 ≈ 17.647% of temp)
    idx_train, idx_val, y_train, y_val = train_test_split(
        idx_temp, y_temp,
        test_size=round(0.15 / 0.85, 6),
        stratify=y_temp,
        random_state=RANDOM_STATE
    )

    return (
        df.iloc[idx_train].reset_index(drop=True), y_train.reset_index(drop=True),
        df.iloc[idx_val ].reset_index(drop=True),  y_val.reset_index(drop=True),
        df.iloc[idx_test].reset_index(drop=True),  y_test.reset_index(drop=True),
    )

# ============================================================
# STAGE 4 — FEATURE ENGINEERING  (fit only on train)
# ============================================================

def build_features(df, tfidf, location_enc, ratings_scaler, fit=False):
    """
    Feature matrix columns:
      [0 : 500]   TF-IDF unigrams+bigrams on Processed_Feedback  ← primary signal
      [500 : 500+L] OHE Location                                  ← contextual
      [-1]          Ratings (scaled, MASKED to 0 when text exists) ← fallback only

    Sentiment_Score is EXCLUDED — it is a lossy projection of the same text
    already captured by TF-IDF, adding no independent information.
    """
    texts        = df['Processed_Feedback'].tolist()
    locations    = df[['Location']].fillna('Unknown')
    ratings_raw  = df['Ratings'].fillna(3.0).values.reshape(-1, 1)
    has_feedback = df['Has_Feedback'].fillna(0).values.reshape(-1, 1)

    if fit:
        text_feats     = tfidf.fit_transform(texts).toarray()
        loc_feats      = location_enc.fit_transform(locations)
        ratings_scaled = ratings_scaler.fit_transform(ratings_raw)
    else:
        text_feats     = tfidf.transform(texts).toarray()
        loc_feats      = location_enc.transform(locations)
        ratings_scaled = ratings_scaler.transform(ratings_raw)

    # Zero out Ratings when feedback exists → conditional fallback feature
    ratings_masked = ratings_scaled * (1 - has_feedback)

    return np.hstack([text_feats, loc_feats, ratings_masked])

# ============================================================
# STAGE 5 — SMOTE  (training set only)
# ============================================================

def apply_smote(X_train, y_train):
    minority_ratio = min(Counter(y_train).values()) / len(y_train)
    if minority_ratio < 0.4:
        smote = SMOTE(random_state=RANDOM_STATE)
        X_bal, y_bal = smote.fit_resample(X_train, y_train)
        print(f"  SMOTE applied  →  {Counter(y_bal)}")
    else:
        X_bal, y_bal = X_train, y_train
        print(f"  Classes balanced, SMOTE skipped  →  {Counter(y_bal)}")
    return X_bal, y_bal

# ============================================================
# STAGE 6 — HYPERPARAMETER SEARCH + CROSS-VALIDATION
# ============================================================

def build_and_tune_model(X_train_bal, y_train_bal):
    """
    RandomizedSearchCV over:
      - architecture (2–3 layers, max 256 neurons)
      - alpha L2 regularisation (1e-4 to 5e-2)
      - learning_rate_init
      - batch_size

    Scoring: F1  (robust to class imbalance)
    CV: Stratified 3-fold

    The best estimator is refitted on the full balanced training set.
    """
    base_mlp = MLPClassifier(
        early_stopping=True,       # stop before memorising
        validation_fraction=0.1,   # internal val for early stop
        n_iter_no_change=15,       # patience
        learning_rate='adaptive',
        max_iter=300,
        random_state=RANDOM_STATE,
    )

    param_dist = {
        'hidden_layer_sizes': [
            (128, 64),
            (256, 128),
            (128, 64, 32),
            (256, 128, 64),
        ],
        'alpha':             [1e-4, 1e-3, 1e-2, 5e-2],
        'learning_rate_init':[1e-3, 5e-4, 1e-4],
        'batch_size':        [32, 64],
    }

    cv = StratifiedKFold(n_splits=3, shuffle=True, random_state=RANDOM_STATE)

    search = RandomizedSearchCV(
        base_mlp,
        param_distributions=param_dist,
        n_iter=6,
        cv=cv,
        scoring='f1',
        n_jobs=-1,
        random_state=RANDOM_STATE,
        refit=True,
        return_train_score=True,
        error_score='raise',
    )
    search.fit(X_train_bal, y_train_bal)

    # ── CV diagnostics ────────────────────────────────────────
    best_idx   = search.best_index_
    cv_results = search.cv_results_
    cv_mean    = cv_results['mean_test_score'][best_idx]
    cv_std     = cv_results['std_test_score'][best_idx]

    print(f"\n  Best CV F1 (mean ± std): {cv_mean:.4f} ± {cv_std:.4f}")
    print(f"  Best params: {search.best_params_}")

    if cv_std > 0.03:
        print("  ⚠ High CV variance — model may be unstable across folds")
    else:
        print("  ✓ Low CV variance — stable generalisation")

    return search.best_estimator_, search.best_params_, cv_mean, cv_std


def build_baseline_model():
    """Create a fast default MLP model for quick training and saving."""
    return MLPClassifier(
        hidden_layer_sizes=(128, 64),
        alpha=1e-3,
        learning_rate_init=1e-3,
        batch_size=32,
        early_stopping=True,
        validation_fraction=0.1,
        n_iter_no_change=15,
        learning_rate='adaptive',
        max_iter=300,
        random_state=RANDOM_STATE,
    )


def train_baseline_model(X_train_bal, y_train_bal):
    """Train the fast baseline model directly without hyperparameter search."""
    model = build_baseline_model()
    model.fit(X_train_bal, y_train_bal)
    return model


# ============================================================
# MODEL PERSISTENCE
# ============================================================

def build_transformers():
    """Create transformers used for training and inference."""
    tfidf = TfidfVectorizer(
        ngram_range=(1, 2),
        max_features=500,
        token_pattern=r'\b\w+\b',
        lowercase=False,
    )
    location_enc = OneHotEncoder(sparse_output=False, handle_unknown='ignore')
    ratings_scaler = MinMaxScaler(feature_range=(0, 1))
    return tfidf, location_enc, ratings_scaler


def save_trained_model(model, tfidf, location_enc, ratings_scaler, model_path):
    """Save model and preprocessing objects to a joblib file."""
    artifact = {
        'model': model,
        'tfidf': tfidf,
        'location_encoder': location_enc,
        'ratings_scaler': ratings_scaler,
    }
    os.makedirs(os.path.dirname(model_path) or '.', exist_ok=True)
    joblib.dump(artifact, model_path)
    return model_path


def load_trained_model(model_path):
    """Load a previously saved model artifact from disk."""
    return joblib.load(model_path)


def load_model_artifact(model_path=MODEL_PATH):
    """Load an existing trained model artifact for API inference."""
    if not os.path.exists(model_path):
        raise FileNotFoundError(
            f"Trained model artifact not found: {model_path}. "
            "Run model training first or use get_trained_model(...) to build it."
        )
    return load_trained_model(model_path)


def preprocess_feedback(feedback):
    """Clean and normalize a single feedback text value."""
    return remove_stopwords(text_preprocessing(feedback))


def build_inference_df(records):
    """Build a DataFrame suitable for prediction from API input records."""
    if isinstance(records, dict):
        records = [records]

    rows = []
    for record in records:
        feedback = record.get('feedback', '')
        ratings = record.get('ratings', np.nan)
        location = record.get('location', 'Unknown')

        processed_feedback = preprocess_feedback(feedback)
        has_feedback = int(len(processed_feedback.split()) >= 3)

        rows.append({
            'Processed_Feedback': processed_feedback,
            'Location': location if location else 'Unknown',
            'Ratings': ratings,
            'Has_Feedback': has_feedback,
        })

    df = pd.DataFrame(rows)
    df['Ratings'] = pd.to_numeric(df['Ratings'], errors='coerce')
    df['Location'] = df['Location'].fillna('Unknown').astype(str)
    df['Processed_Feedback'] = df['Processed_Feedback'].fillna('').astype(str)
    df['Has_Feedback'] = df['Has_Feedback'].fillna(0).astype(int)
    return df


def predict_from_artifact(artifact, records):
    """Predict satisfaction labels for one or more input records."""
    df = build_inference_df(records)
    X = build_features(df, artifact['tfidf'], artifact['location_encoder'], artifact['ratings_scaler'], fit=False)
    y_pred = artifact['model'].predict(X)

    proba = None
    if hasattr(artifact['model'], 'predict_proba'):
        proba = artifact['model'].predict_proba(X)[:, 1]

    results = []
    for idx, pred in enumerate(y_pred):
        entry = {
            'prediction': int(pred),
            'label': 'satisfied' if pred == 1 else 'unsatisfied',
        }
        if proba is not None:
            entry['satisfaction_score'] = float(proba[idx])
        results.append(entry)

    return results


def predict_api(record, model_path=MODEL_PATH):
    """Load the saved artifact and predict a single API input record."""
    artifact = load_model_artifact(model_path)
    result = predict_from_artifact(artifact, record)
    return result[0] if isinstance(record, dict) else result


def get_trained_model(
    excel_path=EXCEL_PATH,
    csv_path=CSV_PATH,
    model_path=MODEL_PATH,
    force_rebuild=False,
    quick_mode=True,
):
    """Train the model and save it as a joblib artifact.

    Returns a dict containing the trained estimator and preprocessing objects.
    """
    if not force_rebuild and os.path.exists(model_path):
        return load_trained_model(model_path)

    if os.path.exists(csv_path):
        df = reload_csv(csv_path)
    else:
        df = build_csv(excel_path, csv_path)

    df_train, y_train, df_val, y_val, df_test, y_test = split_data(df)
    tfidf, location_enc, ratings_scaler = build_transformers()
    X_train = build_features(df_train, tfidf, location_enc, ratings_scaler, fit=True)
    X_train_bal, y_train_bal = apply_smote(X_train, y_train)

    if quick_mode:
        print('Using quick baseline training mode...')
        model = train_baseline_model(X_train_bal, y_train_bal)
    else:
        model, _, _, _ = build_and_tune_model(X_train_bal, y_train_bal)

    save_trained_model(model, tfidf, location_enc, ratings_scaler, model_path)
    return load_trained_model(model_path)


if __name__ == '__main__':
    artifact_path = MODEL_PATH
    artifact = get_trained_model(model_path=artifact_path, force_rebuild=True, quick_mode=True)
    print(f' Trained model saved to {artifact_path}')
    print(f' Artifact keys: {list(artifact.keys())}')