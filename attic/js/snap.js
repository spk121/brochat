document.addEventListener("DOMContentLoaded", function() {
    const fileInput = document.getElementById("fileInput");
    const cameraButton = document.getElementById("cameraButton");
    const previewContainer = document.getElementById("previewContainer");
    const cameraPreview = document.getElementById("cameraPreview");
    const cameraCanvas = document.getElementById("cameraCanvas");
    let mediaStream = null;

    // File/Gallery selection: Generate thumbnails
    fileInput.addEventListener("change", function() {
        previewContainer.innerHTML = ""; // Clear previous thumbnails
        for (const file of fileInput.files) {
            const imgElement = document.createElement("img");
            imgElement.src = URL.createObjectURL(file);
            imgElement.className = "thumbnail";
            imgElement.onclick = () => removeImage(file);
            previewContainer.appendChild(imgElement);
        }
    });

    // Camera activation
    cameraButton.addEventListener("click", async function() {
        if (!mediaStream) {
            mediaStream = await navigator.mediaDevices.getUserMedia({ video: true });
            cameraPreview.srcObject = mediaStream;
            cameraPreview.style.display = "block";
        } else {
            captureImage();
        }
    });

    function captureImage() {
        const context = cameraCanvas.getContext("2d");
        cameraCanvas.width = cameraPreview.videoWidth;
        cameraCanvas.height = cameraPreview.videoHeight;
        context.drawImage(cameraPreview, 0, 0);
        cameraPreview.style.display = "none";

        const imgElement = document.createElement("img");
        imgElement.src = cameraCanvas.toDataURL("image/png");
        imgElement.className = "thumbnail";
        previewContainer.appendChild(imgElement);
    }

    function removeImage(file) {
        fileInput.value = ""; // Reset input to remove selection
        previewContainer.innerHTML = "";
    }
});
