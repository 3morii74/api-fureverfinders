import sys
import cv2
import numpy as np

def extract_features(image_path):
    # Read the image using OpenCV
    image = cv2.imread(image_path)

    # Convert the image to RGB
    image = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)

    # Calculate the color histogram for each channel (R, G, B)
    hist_r = cv2.calcHist([image], [0], None, [256], [0, 256])
    hist_g = cv2.calcHist([image], [1], None, [256], [0, 256])
    hist_b = cv2.calcHist([image], [2], None, [256], [0, 256])

    # Normalize the histograms
    hist_r = cv2.normalize(hist_r, hist_r).flatten()
    hist_g = cv2.normalize(hist_g, hist_g).flatten()
    hist_b = cv2.normalize(hist_b, hist_b).flatten()

    # Concatenate the histograms to form the feature vector
    features = np.concatenate((hist_r, hist_g, hist_b))
    return features.tolist()

if __name__ == "__main__":
    image_path = sys.argv[1]
    features = extract_features(image_path)
    print(features)
