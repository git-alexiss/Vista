import os

import pandas as pd
from flask import Flask, jsonify, request

try:
    from api import predict_api
    from api.model import CSV_PATH
except ImportError:
    import api as api_module
    from model import CSV_PATH
    predict_api = api_module.predict_api

app = Flask(__name__)


def load_municipality_rankings():
    df = pd.read_csv(CSV_PATH)
    if 'Location' not in df.columns or 'Ratings' not in df.columns:
        raise ValueError('Dataset must contain Location and Ratings columns')

    stats = (
        df.groupby('Location', dropna=False)
          .agg(
              average_rating=('Ratings', 'mean'),
              total_reviews=('Ratings', 'count'),
              satisfied_count=('Satisfaction_Label', lambda s: (s == 1).sum()),
          )
          .reset_index()
    )

    stats['average_rating'] = stats['average_rating'].round(3)
    stats['satisfied_pct'] = (stats['satisfied_count'] / stats['total_reviews']).round(3)
    stats = stats.sort_values(
        by=['average_rating', 'total_reviews'],
        ascending=[False, False],
        ignore_index=True,
    )
    stats['rank'] = stats.index + 1

    return stats[['rank', 'Location', 'average_rating', 'total_reviews', 'satisfied_pct']].to_dict(orient='records')


@app.route('/health', methods=['GET'])
def health():
    return jsonify({'status': 'ok'})


@app.route('/municipalities', methods=['GET'])
def municipalities():
    rankings = load_municipality_rankings()
    return jsonify(rankings)


@app.route('/predict', methods=['POST'])
def predict():
    data = request.get_json(force=True)
    result = predict_api(data)
    return jsonify(result)


@app.after_request
def add_cors_headers(response):
    response.headers['Access-Control-Allow-Origin'] = '*'
    response.headers['Access-Control-Allow-Methods'] = 'GET, POST, OPTIONS'
    response.headers['Access-Control-Allow-Headers'] = 'Content-Type, Authorization'
    return response


if __name__ == '__main__':
    host = os.environ.get('FLASK_HOST', '0.0.0.0')
    port = int(os.environ.get('PORT', os.environ.get('FLASK_PORT', 5000)))
    app.run(host=host, port=port)
