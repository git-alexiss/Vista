import os
import model

if __name__ == '__main__':
    model_path = model.MODEL_PATH
    print('MODEL_PATH =', model_path)
    artifact = model.get_trained_model(force_rebuild=True, quick_mode=True)
    print('Artifact keys =', list(artifact.keys()))
    print('Saved =', os.path.exists(model_path))
