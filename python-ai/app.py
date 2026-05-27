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

# --- ImageKit config ---
IMAGEKIT_PRIVATE_KEY = os.environ["IMAGEKIT_PRIVATE_KEY"]  # your private API key
IMAGEKIT_URL_ENDPOINT = os.environ["IMAGEKIT_URL_ENDPOINT"]  # e.g. https://ik.imagekit.io/your_id

model, _, preprocess = open_clip.create_model_and_transforms(
    'ViT-B-32',
    pretrained='laion2b_s34b_b79k'
)
model.eval()

image_features = []
image_names = []
image_urls = []


def fetch_imagekit_files(folder="/variant_images"):
    """Fetch all image file metadata from an ImageKit folder."""
    url = "https://api.imagekit.io/v1/files"
    params = {"path": folder, "fileType": "image", "limit": 500}
    resp = requests.get(url, params=params, auth=(IMAGEKIT_PRIVATE_KEY, ""))
    resp.raise_for_status()
    return resp.json()


def load_image_from_url(url):
    """Download and preprocess an image from a URL."""
    resp = requests.get(url)
    resp.raise_for_status()
    image = Image.open(BytesIO(resp.content)).convert("RGB")
    return preprocess(image).unsqueeze(0)


def build_index():
    """Download all images from ImageKit and encode them."""
    files = fetch_imagekit_files(folder="/variant_images")
    print(f"Found {len(files)} images in ImageKit")

    for file in files:
        try:
            image_tensor = load_image_from_url(file["url"])
            with torch.no_grad():
                feature = model.encode_image(image_tensor, normalize=True)
            image_features.append(feature.numpy())
            image_names.append(file["name"])
            image_urls.append(file["url"])
        except Exception as e:
            print(f"Skipping {file['name']}: {e}")

    print("Index built.")


build_index()
index_matrix = np.vstack(image_features) if image_features else np.array([])


@app.route('/search', methods=['POST'])
def search():
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
                "name": image_names[i],
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
    query = request.json.get("query", "").strip()
    if not query:
        return jsonify({"error": "No query provided"}), 400

    tokenizer = open_clip.get_tokenizer('ViT-B-32')
    text = tokenizer([query])
    with torch.no_grad():
        text_feature = model.encode_text(text).numpy()

    similarities = cosine_similarity(text_feature, index_matrix)[0]
    top_indices = similarities.argsort()[-5:][::-1]

    results = [
        {
            "name": image_names[i],
            "url": image_urls[i],
            "similarity": round(float(similarities[i]), 4)
        }
        for i in top_indices
    ]
    return jsonify(results)


if __name__ == '__main__':
    app.run(port=5000)