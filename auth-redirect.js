// auth-redirect.js - Hệ thống phân quyền và chuyển hướng
(function() {
    'use strict';

    // Lấy thông tin user position
    function getUserPosition() {
        return localStorage.getItem('userPosition') || '';
    }

    function isAdmin() {
        const pos = getUserPosition().toLowerCase();
        // Sau khi chuẩn hoá trên server, chỉ còn 'quan-tri-vien' (hoặc 'admin' cũ)
            return pos === 'quan-tri-vien' || pos === 'admin';
        }

        function isSessionLoggedIn() {
            return sessionStorage.getItem('sessionLoggedIn') === 'true';
        }
    }

    // Lưu position khi đăng nhập
    window.saveUserPosition = function(position) {
        if (position) {
            localStorage.setItem('userPosition', position);
        }
    };

    // Xóa position khi logout
    window.clearUserPosition = function() {
        localStorage.removeItem('userPosition');
    };

    // Kiểm tra và chuyển hướng menu
    window.checkUserPosition = function() {
        const admin = isAdmin();
        
        // Cập nhật tất cả link menu
            updateMenuLinks(admin && isSessionLoggedIn());
        
        // Chỉ thêm visual indicator nếu đã đăng nhập VÀ là admin
        const accountDropdown = document.querySelector('.account-dropdown');
        const isLoggedIn = accountDropdown && accountDropdown.classList.contains('logged-in');
        
        if (admin && isLoggedIn) {
            addAdminIndicator();
        } else {
            removeAdminIndicator();
        }
    };

    // Cập nhật các link menu dựa trên position
    function updateMenuLinks(isAdminUser) {
        const menuLinks = document.querySelectorAll('a[href]');
        
        menuLinks.forEach(link => {
            const href = link.getAttribute('href');
            
            if (!href || href.startsWith('#') || href.startsWith('http')) {
                return; // Bỏ qua anchor links và external links
            }

            // Chuyển đổi link dựa trên position
            if (isAdminUser) {
                // Quản trị viên: chuyển sang phiên bản admin
                if (href.includes('index.html') && !href.includes('quan-tri-vien')) {
                    link.setAttribute('data-original-href', href);
                    link.setAttribute('href', 'quan-tri-vien-index.html');
                } else if (href.includes('thong-tin.html') && !href.includes('quan-tri-vien')) {
                    link.setAttribute('data-original-href', href);
                    link.setAttribute('href', 'quan-tri-vien-thongtin.html');
                } else if (href.includes('tin-tuc.html') && !href.includes('quan-tri-vien')) {
                    link.setAttribute('data-original-href', href);
                    link.setAttribute('href', 'quan-tri-vien-tintuc.html');
                }
            } else {
                // Khách hàng: chuyển về phiên bản thường
                if (href.includes('quan-tri-vien-index.html')) {
                    link.setAttribute('data-original-href', href);
                    link.setAttribute('href', 'index.html');
                } else if (href.includes('quan-tri-vien-thongtin.html')) {
                    link.setAttribute('data-original-href', href);
                    link.setAttribute('href', 'thong-tin.html');
                } else if (href.includes('quan-tri-vien-tintuc.html')) {
                    link.setAttribute('data-original-href', href);
                    link.setAttribute('href', 'tin-tuc.html');
                }
            }
        });
    }

    // Thêm indicator cho admin
    function addAdminIndicator() {
        // Thêm badge vào nút account
        const accountBtn = document.querySelector('.account-btn');
        if (accountBtn && !accountBtn.querySelector('.admin-badge')) {
            const badge = document.createElement('span');
            badge.className = 'admin-badge';
            badge.innerText = 'ADMIN';
            badge.style.cssText = 'position: absolute; top: -5px; right: -5px; background: #ff6b6b; color: white; font-size: 8px; padding: 2px 4px; border-radius: 3px; font-weight: bold;';
            accountBtn.style.position = 'relative';
            accountBtn.appendChild(badge);
        }
    }

    // Xóa indicator admin
    function removeAdminIndicator() {
        const badge = document.querySelector('.admin-badge');
        if (badge) {
            badge.remove();
        }
    }

    // Xóa indicator admin
    function removeAdminIndicator() {
        const badge = document.querySelector('.admin-badge');
        if (badge) {
            badge.remove();
        }

        const header = document.querySelector('.header');
        if (header) {
            header.style.background = 'linear-gradient(135deg, #63e94b, #3fe009)';
        }
    }

    // Kiểm tra quyền truy cập file quản trị
    window.checkAdminAccess = function() {
        const currentPage = window.location.pathname.split('/').pop();
        const adminPages = ['quan-tri-vien-index.html', 'quan-tri-vien-thongtin.html', 'quan-tri-vien-tintuc.html'];
        
        if (adminPages.includes(currentPage)) {
            // Đây là trang quản trị, kiểm tra quyền
                if (!(isAdmin() && isSessionLoggedIn())) {
                // Không phải admin, chuyển hướng
                const normalPage = currentPage.replace('quan-tri-vien-', '').replace('thongtin', 'thong-tin');
                alert('Bạn không có quyền truy cập trang quản trị!');
                window.location.href = normalPage;
                return false;
            }
        }
        return true;
    };

    // Auto-redirect nếu admin truy cập trang thường
    window.autoRedirectAdmin = function() {
            if (isAdmin() && isSessionLoggedIn()) {
            const currentPage = window.location.pathname.split('/').pop();
            
            if (currentPage === 'index.html') {
                window.location.href = 'quan-tri-vien-index.html';
            } else if (currentPage === 'thong-tin.html') {
                window.location.href = 'quan-tri-vien-thongtin.html';
            }
        }
    };

    // Xử lý khi trang load
    document.addEventListener('DOMContentLoaded', function() {
        // Kiểm tra quyền truy cập
        if (!checkAdminAccess()) {
            return; // Đã chuyển hướng rồi
        }

        // Cập nhật menu
        checkUserPosition();
    });

    // Lắng nghe sự kiện storage để đồng bộ giữa các tab
    window.addEventListener('storage', function(e) {
        if (e.key === 'userPosition') {
            checkUserPosition();
            
            // Nếu logout, reload trang
            if (!e.newValue) {
                window.location.reload();
            }
        }
    });

})();
