// ========== IMAGE UPLOAD HANDLERS ==========

let thumbnailFile = null;
let galleryFiles = [];
let thumbnailExistingPath = '';

// Reset image uploads
function resetImageUploads() {
    thumbnailFile = null;
    galleryFiles = [];
    thumbnailExistingPath = '';
    const thumbnailInput = document.getElementById('thumbnailInput');
    const galleryInput = document.getElementById('galleryInput');
    const thumbnailPreview = document.getElementById('thumbnailPreview');
    const galleryPreview = document.getElementById('galleryPreview');
    const thumbnailPath = document.getElementById('thumbnailPath');
    const galleryPaths = document.getElementById('galleryPaths');
    const addMoreBtn = document.getElementById('addMoreBtn');
    
    if (thumbnailInput) thumbnailInput.value = '';
    if (galleryInput) galleryInput.value = '';
    if (thumbnailPreview) thumbnailPreview.innerHTML = '';
    if (galleryPreview) galleryPreview.innerHTML = '';
    if (thumbnailPath) thumbnailPath.value = '';
    if (galleryPaths) galleryPaths.value = '';
    if (addMoreBtn) addMoreBtn.style.display = 'none';
}

// Upload hình chính (Thumbnail)
function initThumbnailUpload() {
    const thumbnailInput = document.getElementById('thumbnailInput');
    if (!thumbnailInput) return;
    
    thumbnailInput.addEventListener('change', async function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        if (!file.type.startsWith('image/')) {
            alert('Vui lòng chọn file hình ảnh!');
            return;
        }
        
        if (file.size > 2 * 1024 * 1024) {
            alert('Hình ảnh phải nhỏ hơn 2MB!');
            return;
        }
        
        thumbnailFile = file;
        thumbnailExistingPath = '';
        const previewContainer = document.getElementById('thumbnailPreview');
        
        // Show loading
        previewContainer.innerHTML = '<div class="image-upload-loading">⏳ Đang tải lên...</div>';
        
        try {
            // Upload to server
            const formData = new FormData();
            formData.append('image', file);
            formData.append('folderPath', 'hinh-mau/');
            
            const response = await fetch('/upload_image.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.status === 'success' && result.path) {
                // Store path
                document.getElementById('thumbnailPath').value = result.path;
                thumbnailExistingPath = result.path;
                
                // Show preview
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewContainer.innerHTML = `
                        <div class="image-preview-item primary">
                            <span class="image-preview-badge">Hình chính</span>
                            <img src="${e.target.result}" alt="Thumbnail">
                            <button type="button" class="image-preview-remove" onclick="removeThumbnail()">&times;</button>
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            } else {
                alert('Lỗi tải hình: ' + (result.message || 'Unknown error'));
                previewContainer.innerHTML = '';
            }
        } catch (error) {
            console.error('Upload error:', error);
            alert('Không thể tải hình lên. Vui lòng thử lại.');
            previewContainer.innerHTML = '';
        }
    });
}

// Upload hình phụ (Gallery)
function initGalleryUpload() {
    const galleryInput = document.getElementById('galleryInput');
    if (!galleryInput) return;
    
    galleryInput.addEventListener('change', async function(e) {
        const files = Array.from(e.target.files);
        if (files.length === 0) return;
        
        // Check limit
        const currentCount = galleryFiles.length;
        const newCount = files.length;
        
        if (currentCount + newCount > 10) {
            alert(`Chỉ được tải tối đa 10 hình phụ! Hiện có ${currentCount} hình.`);
            return;
        }
        
        const previewContainer = document.getElementById('galleryPreview');
        
        for (const file of files) {
            if (!file.type.startsWith('image/')) continue;
            if (file.size > 2 * 1024 * 1024) {
                alert(`File ${file.name} quá lớn (>2MB), bỏ qua.`);
                continue;
            }
            
            // Show loading
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'image-preview-item';
            loadingDiv.innerHTML = '<div class="image-upload-loading">⏳</div>';
            previewContainer.appendChild(loadingDiv);
            
            try {
                // Upload to server
                const formData = new FormData();
                formData.append('image', file);
                formData.append('folderPath', 'hinh-mau/');
                
                const response = await fetch('/upload_image.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.status === 'success' && result.path) {
                    // Store file info
                    galleryFiles.push({
                        file: file,
                        path: result.path
                    });
                    
                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const index = galleryFiles.length - 1;
                        loadingDiv.innerHTML = `
                            <img src="${e.target.result}" alt="Gallery ${index + 1}">
                            <button type="button" class="image-preview-remove" onclick="removeGalleryImage(${index})">&times;</button>
                        `;
                    };
                    reader.readAsDataURL(file);
                    
                    // Update hidden input
                    updateGalleryPaths();
                } else {
                    previewContainer.removeChild(loadingDiv);
                    alert('Lỗi tải hình: ' + (result.message || 'Unknown error'));
                }
            } catch (error) {
                console.error('Upload error:', error);
                previewContainer.removeChild(loadingDiv);
                alert('Không thể tải hình lên. Vui lòng thử lại.');
            }
        }
        
        // Show "Add more" button if less than 10
        if (galleryFiles.length < 10 && galleryFiles.length > 0) {
            document.getElementById('addMoreBtn').style.display = 'inline-flex';
        }
        
        // Reset input
        e.target.value = '';
    });
}

// Remove thumbnail
function removeThumbnail() {
    thumbnailFile = null;
    thumbnailExistingPath = '';
    document.getElementById('thumbnailInput').value = '';
    document.getElementById('thumbnailPreview').innerHTML = '';
    document.getElementById('thumbnailPath').value = '';
}

// Remove gallery image
function removeGalleryImage(index) {
    galleryFiles.splice(index, 1);
    updateGalleryPaths();
    renderGalleryPreviews();
    
    if (galleryFiles.length < 10) {
        const addMoreBtn = document.getElementById('addMoreBtn');
        if (addMoreBtn) {
            addMoreBtn.style.display = galleryFiles.length > 0 ? 'inline-flex' : 'none';
        }
    }
}

// Update gallery paths hidden input
function updateGalleryPaths() {
    const paths = galleryFiles.map(item => item.path);
    const galleryPathsInput = document.getElementById('galleryPaths');
    if (galleryPathsInput) {
        galleryPathsInput.value = JSON.stringify(paths);
    }
}

// Re-render gallery previews
function renderGalleryPreviews() {
    const container = document.getElementById('galleryPreview');
    if (!container) return;
    
    container.innerHTML = '';
    
    galleryFiles.forEach((item, index) => {
        const div = document.createElement('div');
        div.className = 'image-preview-item';
        const src = item.file ? null : item.path;
        if (item.file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                div.innerHTML = `
                    <img src="${e.target.result}" alt="Gallery ${index + 1}">
                    <button type="button" class="image-preview-remove" onclick="removeGalleryImage(${index})">&times;</button>
                `;
            };
            reader.readAsDataURL(item.file);
        } else {
            div.innerHTML = `
                <img src="${src}" alt="Gallery ${index + 1}">
                <button type="button" class="image-preview-remove" onclick="removeGalleryImage(${index})">&times;</button>
            `;
        }
        container.appendChild(div);
    });
}

// Initialize all image upload handlers
function initImageUploadHandlers() {
    initThumbnailUpload();
    initGalleryUpload();
}

// Set initial images for EDIT mode
function setInitialThumbnail(path) {
    const previewContainer = document.getElementById('thumbnailPreview');
    const thumbPathInput = document.getElementById('thumbnailPath');
    if (!previewContainer || !thumbPathInput) return;
    if (!path) {
        removeThumbnail();
        return;
    }
    thumbnailFile = null;
    thumbnailExistingPath = path;
    thumbPathInput.value = path;
    previewContainer.innerHTML = `
        <div class="image-preview-item primary">
            <span class="image-preview-badge">Hình chính</span>
            <img src="${path}" alt="Thumbnail">
            <button type="button" class="image-preview-remove" onclick="removeThumbnail()">&times;</button>
        </div>
    `;
}

function setInitialGallery(pathsArray) {
    galleryFiles = [];
    if (Array.isArray(pathsArray)) {
        galleryFiles = pathsArray.slice(0, 10).map(p => ({ file: null, path: p }));
    }
    updateGalleryPaths();
    renderGalleryPreviews();
    const addMoreBtn = document.getElementById('addMoreBtn');
    if (addMoreBtn) {
        addMoreBtn.style.display = galleryFiles.length > 0 && galleryFiles.length < 10 ? 'inline-flex' : (galleryFiles.length === 0 ? 'none' : 'none');
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initImageUploadHandlers);
} else {
    initImageUploadHandlers();
}
