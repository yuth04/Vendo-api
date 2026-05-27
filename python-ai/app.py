from flask import Flask, request, jsonify
from PIL import Image
import torch
import open_clip
import numpy as np
from sklearn.metrics.pairwise import cosine_similarity
import requests
from io import BytesIO
import os
from dotenv import load_dotenv

load_dotenv()

app = Flask(__name__)

LARAVEL_API_URL = os.environ["LARAVEL_API_URL"]  # e.g. http://localhost:8000

model, _, preprocess = open_clip.create_model_and_transforms(
    'ViT-B-32',
    pretrained='laion2b_s34b_b79k'
)
model.eval()
tokenizer = open_clip.get_tokenizer('ViT-B-32')

image_features = []
image_urls = []


def fetch_images_from_db():
    """Fetch all product image URLs from Laravel DB endpoint."""
    resp = requests.get(f"{LARAVEL_API_URL}/public/product-images/all")
    resp.raise_for_status()
    return resp.json()  # returns list of { id, image }


def load_image_from_url(url):
    resp = requests.get(url)
    resp.raise_for_status()
    image = Image.open(BytesIO(resp.content)).convert("RGB")
    return preprocess(image).unsqueeze(0)


def build_index():
    global image_features, image_urls
    image_features, image_urls = [], []

    images = fetch_images_from_db()
    print(f"Found {len(images)} images in DB")

    for item in images:
        try:
            image_tensor = load_image_from_url(item["image"])
            with torch.no_grad():
                feature = model.encode_image(image_tensor, normalize=True)
            image_features.append(feature.numpy())
            image_urls.append(item["image"])
        except Exception as e:
            print(f"Skipping {item['image']}: {e}")

    print(f"Index built with {len(image_urls)} images.")


build_index()
index_matrix = np.vstack(image_features) if image_features else np.array([])


@app.route('/search', methods=['POST'])
def search():
    if index_matrix.size == 0:
        return jsonify({"error": "Index is empty"}), 503

    if 'image' not in request.files:
        return jsonify({"error": "No image provided"}), 400

    try:
        image_tensor = preprocess(
            Image.open(request.files['image']).convert("RGB")
        ).unsqueeze(0)

        with torch.no_grad():
            query_feature = model.encode_image(image_tensor, normalize=True).numpy()

        similarities = cosine_similarity(query_feature, index_matrix)[0]
        top_indices = similarities.argsort()[-5:][::-1]

        results = [
            {
                "name": image_urls[i],
                "url": image_urls[i],
                "similarity": round(float(similarities[i]), 4)
            }
            for i in top_indices
        ]
        return jsonify(results)

    except Exception as e:
        return jsonify({"error": str(e)}), 500


@app.route('/search/text', methods=['POST'])
def search_by_text():
    data = request.get_json()
    if not data or not data.get("query", "").strip():
        return jsonify({"error": "No query provided"}), 400

    text = tokenizer([data["query"].strip()])
    with torch.no_grad():
        text_feature = model.encode_text(text, normalize=True).numpy()

    similarities = cosine_similarity(text_feature, index_matrix)[0]
    top_indices = similarities.argsort()[-5:][::-1]

    results = [
        {
            "name": image_urls[i],
            "url": image_urls[i],
            "similarity": round(float(similarities[i]), 4)
        }
        for i in top_indices
    ]
    return jsonify(results)


@app.route('/refresh', methods=['POST'])
def refresh():
    global index_matrix
    build_index()
    index_matrix = np.vstack(image_features) if image_features else np.array([])
    return jsonify({"message": f"Index rebuilt with {len(image_urls)} images"})


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=8000)