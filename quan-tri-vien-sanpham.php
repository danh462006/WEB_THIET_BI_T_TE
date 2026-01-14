<?php
// Admin Product Manager - Incremental Update
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản Trị Viên - Cập nhật sản phẩm</title>
    <link rel="icon" type="image/png" href="hinh-anh/logo-favicon.png">
    <link rel="stylesheet" href="style.css">
    <script src="auth-redirect.js"></script>
    <script src="image-upload-handler.js" defer></script>
    <style>
        body { background: #f6f7fb; color: #111827; }
        .admin-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; padding: 14px 20px; font-weight: 600; border-radius: 8px; margin: 16px auto; max-width: 1200px;
        }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 16px; }
        .filters { display: flex; gap: 12px; align-items: center; background: #fff; border: 1px solid #e5e7eb; padding: 12px; border-radius: 8px; margin-bottom: 12px; }
        .filters input[type="text"] { flex: 1; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 6px; }
        .filters label { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; }
        .btn { padding: 8px 14px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .btn-primary { background: #0d6efd; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
        .table th, .table td { padding: 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; text-align: left; }
        .table th { background: #f9fafb; font-weight: 700; }
        .badge-ok { background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 999px; font-weight: 700; font-size: 12px; }
        .badge-miss { background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 999px; font-weight: 700; font-size: 12px; }
        .actions { display: flex; gap: 8px; }

        /* Modal */
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; }
        .modal .content { width: 100%; max-width: 1000px; background: #fff; margin: 40px auto; border-radius: 10px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
        .modal .header { padding: 14px 16px; background: #111827; color: #fff; display: flex; align-items: center; justify-content: space-between; }
        .modal .body { padding: 16px; max-height: 70vh; overflow: auto; }
        .modal .footer { padding: 14px 16px; background: #f9fafb; display: flex; gap: 10px; justify-content: flex-end; }

        /* Tabs */
        .tabs { display: flex; border-bottom: 1px solid #e5e7eb; }
        .tab { padding: 10px 14px; cursor: pointer; font-weight: 600; color: #374151; }
        .tab.active { color: #0d6efd; border-bottom: 3px solid #0d6efd; }
        .tab-panel { display: none; padding-top: 12px; }
        .tab-panel.active { display: block; }

        .form-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #e5e7eb; border-radius: 6px; }
        textarea.small { min-height: 80px; }
        textarea.large { min-height: 140px; }

        /* Image Upload Styles (reused) */
        .image-upload-section { background: #f9fafb; border: 2px dashed #d1d5db; border-radius: 8px; padding: 20px; margin: 12px 0; }
        .image-upload-box { position: relative; border: 2px dashed #9ca3af; border-radius: 8px; padding: 20px; text-align: center; background: white; cursor: pointer; transition: all 0.3s; }
        .image-upload-box:hover { border-color: #3b82f6; background: #eff6ff; }
        .image-upload-box input[type=file] { display: none; }
        .image-preview-container { margin-top: 12px; display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 12px; }
        .image-preview-item { position: relative; width: 120px; height: 120px; border-radius: 8px; overflow: hidden; border: 2px solid #e5e7eb; background: #f3f4f6; }
        .image-preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .image-preview-badge { position: absolute; top: 4px; left: 4px; background: #3b82f6; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; }
        .image-upload-loading { position: absolute; inset: 0; background: rgba(255,255,255,0.9); display: flex; align-items: center; justify-content: center; color: #0d6efd; font-weight: 700; }
        .image-preview-remove { position: absolute; top: 4px; right: 4px; width: 24px; height: 24px; border-radius: 50%; background: #ef4444; color: #fff; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .add-more-images-btn { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border: 2px dashed #0d6efd; color: #0d6efd; background: #fff; border-radius: 6px; font-weight: 700; margin-top: 8px; }

        .help { color: #6b7280; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <div class="admin-banner">QUẢN TRỊ VIÊN - CẬP NHẬT SẢN PHẨM</div>

    <div class="filters">
        <input type="text" id="searchInput" placeholder="Tìm theo tên hoặc SKU..." />
        <label><input type="checkbox" id="missingOnly"> Chỉ hiện sản phẩm thiếu thông tin</label>
        <label><input type="checkbox" id="noImageOnly"> Chỉ hiện sản phẩm chưa có ảnh</label>
        <button class="btn btn-primary" id="reloadBtn">Lọc</button>
    </div>

    <table class="table" id="productTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Tên sản phẩm</th>
                <th>SKU</th>
                <th>Giá</th>
                <th>Trạng thái</th>
                <th>Hoàn thiện</th>
                <th>Hành động</th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<!-- Modal Edit -->
<div class="modal" id="editModal">
    <div class="content">
        <div class="header">
            <div>CẬP NHẬT SẢN PHẨM</div>
            <button class="btn btn-secondary" onclick="closeEditModal()">Đóng</button>
        </div>
        <div class="body">
            <div class="tabs">
                <div class="tab active" data-tab="basic">THÔNG TIN CƠ BẢN</div>
                <div class="tab" data-tab="images">HÌNH ẢNH</div>
                <div class="tab" data-tab="seo">SEO & META</div>
            </div>

            <form id="editForm">
                <input type="hidden" id="editId" name="id" />

                <div class="tab-panel active" id="tab-basic">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tên sản phẩm</label>
                            <input type="text" id="name" name="name" required />
                        </div>
                        <div class="form-group">
                            <label>SKU</label>
                            <input type="text" id="sku" name="sku" />
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Loại sản phẩm</label>
                            <select id="type_code" name="type_code">
                                <option value="">-- Chọn loại --</option>
                            </select>
                            <div class="help">Nếu danh mục chưa tải, chọn sau.</div>
                        </div>
                        <div class="form-group">
                            <label>Trạng thái</label>
                            <select id="status" name="status">
                                <option value="active">Đang bán</option>
                                <option value="inactive">Ngưng bán</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Giá gốc</label>
                            <input type="number" step="0.01" id="original_price" name="original_price" />
                        </div>
                        <div class="form-group">
                            <label>Giá bán</label>
                            <input type="number" step="0.01" id="sale_price" name="sale_price" />
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tồn kho</label>
                            <input type="number" id="stock_quantity" name="stock_quantity" />
                        </div>
                        <div class="form-group" style="display:flex; align-items:flex-end; gap:10px;">
                            <label style="flex:1;">&nbsp;</label>
                            <label style="display:flex; gap:8px; align-items:center;">
                                <input type="checkbox" id="create_cache" /> Cập nhật cache từ thông tin này
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Mô tả ngắn</label>
                        <textarea id="short_description" name="short_description" class="small"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Mô tả dài</label>
                        <textarea id="long_description" name="long_description" class="large"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Thông số kỹ thuật</label>
                        <textarea id="technical_specs" name="technical_specs" class="large"></textarea>
                    </div>
                </div>

                <div class="tab-panel" id="tab-images">
                    <div class="image-upload-section">
                        <h3>Hình chính (Thumbnail)</h3>
                        <div class="image-upload-box" onclick="document.getElementById('thumbnailInput').click()">
                            <input type="file" id="thumbnailInput" accept="image/*">
                            <div>Click để chọn hình chính</div>
                        </div>
                        <div id="thumbnailPreview" class="image-preview-container"></div>
                        <input type="hidden" id="thumbnailPath" name="thumbnail_path">
                    </div>

                    <div class="image-upload-section">
                        <h3>Hình phụ (tối đa 10)</h3>
                        <div class="image-upload-box" onclick="document.getElementById('galleryInput').click()">
                            <input type="file" id="galleryInput" accept="image/*" multiple>
                            <div>Click để chọn nhiều hình phụ</div>
                        </div>
                        <div id="galleryPreview" class="image-preview-container"></div>
                        <input type="hidden" id="galleryPaths" name="gallery_paths">
                        <button type="button" class="add-more-images-btn" id="addMoreBtn" onclick="document.getElementById('galleryInput').click()" style="display:none;">➕ Thêm hình phụ</button>
                    </div>
                </div>

                <div class="tab-panel" id="tab-seo">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Slug</label>
                            <input type="text" id="slug" name="slug" />
                            <div class="help">Tự tạo từ tên sản phẩm hoặc chỉnh tay.</div>
                        </div>
                        <div class="form-group">
                            <label>Meta Title</label>
                            <input type="text" id="meta_title" name="meta_title" />
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Meta Description</label>
                        <textarea id="meta_description" name="meta_description" class="small"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Meta Keywords</label>
                        <input type="text" id="meta_keywords" name="meta_keywords" />
                    </div>
                    <div class="help">URL xem trước: <span id="urlPreview">/san-pham/</span></div>
                </div>
            </form>
        </div>
        <div class="footer">
            <button class="btn btn-danger" id="deleteBtn">XÓA SẢN PHẨM</button>
            <button class="btn btn-primary" id="saveBtn">CẬP NHẬT</button>
        </div>
    </div>
</div>

<script>
    // Tabs
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('tab')) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            e.target.classList.add('active');
            const tab = e.target.getAttribute('data-tab');
            document.getElementById('tab-' + tab).classList.add('active');
        }
    });

    // Slug generator
    function slugify(str) {
        return (str || '')
            .toString().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '-')
            .replace(/\s+/g, '-')
            .replace(/-+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
    function updateUrlPreview() {
        const id = document.getElementById('editId').value || 'id';
        const slug = document.getElementById('slug').value || 'ten-san-pham';
        document.getElementById('urlPreview').textContent = `/san-pham/${slug}-${id}`;
    }
    document.addEventListener('input', function(e) {
        if (e.target.id === 'name') {
            const current = document.getElementById('slug').value;
            if (!current) {
                document.getElementById('slug').value = slugify(e.target.value);
                updateUrlPreview();
            }
        }
        if (e.target.id === 'slug') updateUrlPreview();
    });

    // Load type options from localStorage categoryData (if available)
    function loadTypeOptions(selectedCode) {
        const sel = document.getElementById('type_code');
        let optionsHTML = '<option value="">-- Chọn loại --</option>';
        try {
            const raw = localStorage.getItem('dp_admin_categoryData_v1');
            const cd = raw ? JSON.parse(raw) : (window.categoryData || {});
            let index = 1;
            for (const group in cd) {
                const types = cd[group] || [];
                if (!types.length) continue;
                optionsHTML += `<optgroup label="${group}">`;
                for (const t of types) {
                    const val = index; // local mapping only
                    optionsHTML += `<option value="${val}">${t}</option>`;
                    index++;
                }
                optionsHTML += '</optgroup>';
            }
        } catch (e) {}
        sel.innerHTML = optionsHTML;
        if (selectedCode) sel.value = selectedCode;
    }

    // Load list
    async function loadList() {
        const search = document.getElementById('searchInput').value.trim();
        const missingOnly = document.getElementById('missingOnly').checked ? '1' : '0';
        const noImageOnly = document.getElementById('noImageOnly').checked ? '1' : '0';
        const url = new URL(location.origin + location.pathname.replace(/\\\\/g,'/'));
        const qs = new URLSearchParams({ search, missing_only: missingOnly, no_image_only: noImageOnly });
        const res = await fetch('/api/admin_product_list.php?' + qs.toString());
        const data = await res.json();
        const tbody = document.querySelector('#productTable tbody');
        tbody.innerHTML = '';
        if (!data.success) return;
        for (const p of data.data) {
            const tr = document.createElement('tr');
            const price = p.sale_price || p.original_price || 0;
            tr.innerHTML = `
                <td>${p.id}</td>
                <td>${p.name}</td>
                <td>${p.sku || ''}</td>
                <td>${price.toLocaleString('vi-VN')}</td>
                <td>${p.status || ''}</td>
                <td>${p.is_complete ? '<span class="badge-ok">✅ Đã đầy đủ</span>' : '<span class="badge-miss">⚠️ Thiếu thông tin</span>'}</td>
                <td class="actions"><button class="btn btn-primary" data-action="detail" data-id="${p.id}">Chi tiết</button></td>
            `;
            tbody.appendChild(tr);
        }
    }

    document.getElementById('reloadBtn').addEventListener('click', loadList);
    document.addEventListener('DOMContentLoaded', async function(){
        await loadList();
        const params = new URLSearchParams(location.search);
        const pid = params.get('id');
        if (pid) {
            openEditModal(pid);
        }
    });

    // Open/Close modal
    async function openEditModal(id) {
        const modal = document.getElementById('editModal');
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        // Reset images state
        if (typeof resetImageUploads === 'function') resetImageUploads();
        // Load type options
        loadTypeOptions();
        // Fetch product detail
        const res = await fetch('/api/admin_product_get.php?id=' + id);
        const out = await res.json();
        if (!out.success) { alert('Không tải được dữ liệu'); return; }
        const p = out.data;
        // Fill fields
        document.getElementById('editId').value = p.id;
        document.getElementById('name').value = p.name || '';
        document.getElementById('sku').value = p.sku || '';
        document.getElementById('original_price').value = p.original_price || '';
        document.getElementById('sale_price').value = p.sale_price || '';
        document.getElementById('stock_quantity').value = p.stock_quantity || '';
        document.getElementById('status').value = p.status || 'active';
        document.getElementById('short_description').value = p.short_description || '';
        document.getElementById('long_description').value = p.long_description || '';
        document.getElementById('technical_specs').value = p.technical_specs || '';
        document.getElementById('slug').value = p.slug || '';
        document.getElementById('meta_title').value = p.meta_title || '';
        document.getElementById('meta_description').value = p.meta_description || '';
        document.getElementById('meta_keywords').value = p.meta_keywords || '';
        loadTypeOptions(p.type_code);
        updateUrlPreview();
        // Images
        if (typeof setInitialThumbnail === 'function') setInitialThumbnail(p.thumbnail_path || '');
        if (typeof setInitialGallery === 'function') setInitialGallery(p.gallery_paths || []);
    }
    function closeEditModal() {
        const modal = document.getElementById('editModal');
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    document.addEventListener('click', function(e){
        if (e.target.dataset && e.target.dataset.action === 'detail') {
            const id = e.target.dataset.id;
            openEditModal(id);
        }
    });

    // Save
    document.getElementById('saveBtn').addEventListener('click', async function(){
        const payload = {
            id: parseInt(document.getElementById('editId').value, 10),
            name: document.getElementById('name').value.trim(),
            sku: document.getElementById('sku').value.trim(),
            type_code: document.getElementById('type_code').value || null,
            original_price: document.getElementById('original_price').value || null,
            sale_price: document.getElementById('sale_price').value || null,
            stock_quantity: document.getElementById('stock_quantity').value || null,
            status: document.getElementById('status').value,
            short_description: document.getElementById('short_description').value,
            long_description: document.getElementById('long_description').value,
            technical_specs: document.getElementById('technical_specs').value,
            thumbnail_path: document.getElementById('thumbnailPath').value,
            gallery_paths: (()=>{ try { return JSON.parse(document.getElementById('galleryPaths').value || '[]'); } catch(e){ return []; } })(),
            slug: document.getElementById('slug').value.trim(),
            meta_title: document.getElementById('meta_title').value.trim(),
            meta_description: document.getElementById('meta_description').value.trim(),
            meta_keywords: document.getElementById('meta_keywords').value.trim(),
            create_cache: document.getElementById('create_cache').checked ? 1 : 0
        };
        const res = await fetch('/api/admin_product_update.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
        const out = await res.json();
        if (out.success) {
            closeEditModal();
            await loadList();
        } else {
            alert('Cập nhật thất bại');
        }
    });

    // Delete
    document.getElementById('deleteBtn').addEventListener('click', async function(){
        if (!confirm('Bạn chắc chắn muốn xóa sản phẩm này?')) return;
        const id = parseInt(document.getElementById('editId').value, 10);
        const fd = new FormData(); fd.append('id', id);
        const res = await fetch('/api/admin_product_delete.php', { method: 'POST', body: fd });
        const out = await res.json();
        if (out.success) { closeEditModal(); await loadList(); } else { alert('Xóa thất bại'); }
    });

    // Close on outside click
    document.getElementById('editModal').addEventListener('click', function(e){
        if (e.target.id === 'editModal') closeEditModal();
    });
</script>
</body>
</html>
