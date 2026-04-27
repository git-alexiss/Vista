try:
    from .model import MODEL_PATH, load_model_artifact, predict_from_artifact
except ImportError:
    from model import MODEL_PATH, load_model_artifact, predict_from_artifact

_artifact = None


def get_artifact(model_path=None):
    """Load and cache the trained model artifact for API use."""
    global _artifact
    if _artifact is None:
        if model_path is None:
            model_path = MODEL_PATH
        _artifact = load_model_artifact(model_path)
    return _artifact


def predict_api(record, model_path=None):
    """Predict a single input record or a list of records."""
    artifact = get_artifact(model_path)
    return predict_from_artifact(artifact, record)
