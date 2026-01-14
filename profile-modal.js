// ========== PROFILE MANAGEMENT FUNCTIONS ==========

// Open/Close Profile Modal
function openProfileModal() {
    document.getElementById('profileModal').style.display = 'block';
    const accountMenu = document.getElementById('accountMenu');
    if (accountMenu) accountMenu.style.display = 'none'; // Đóng menu tài khoản
    loadProfileData();
}

function closeProfileModal() {
    document.getElementById('profileModal').style.display = 'none';
}

// Toggle password visibility
function togglePasswordVisibility() {
    const passwordField = document.getElementById('profilePassword');
    if (passwordField.type === 'password') {
        passwordField.type = 'text';
    } else {
        passwordField.type = 'password';
    }
}

// Load profile data from server
function loadProfileData() {
    fetch('/get_profile.php')
        .then(response => response.json())
        .then(data => {
            console.log('Profile data received:', data);
            if (data.status === 'success') {
                document.getElementById('profileUsername').value = data.username || '';
                document.getElementById('profilePhone').value = data.phone || '';
                document.getElementById('profileEmail').value = data.email || '';
                // Initialize structured address UI if not already
                initializeAddressFields()
                    .then(() => populateAddressFields(data.address || ''))
                    .catch(() => {
                        // Fallback to original textarea if initialization failed
                        const addrEl = document.getElementById('profileAddress');
                        if (addrEl) addrEl.value = data.address || '';
                    });
                document.getElementById('profilePassword').value = data.password || '••••••••';
                
                // Load avatar
                if (data.avatar && data.avatar !== '') {
                    document.getElementById('profileAvatar').src = data.avatar;
                }
            } else {
                console.error('Error:', data.message);
            }
        })
        .catch(error => console.error('Error loading profile:', error));
}

// Enable editing for username
function enableEdit(fieldId) {
    const field = document.getElementById(fieldId);
    field.readOnly = false;
    field.focus();
    field.style.backgroundColor = '#fff';
}

// Handle avatar upload
function handleAvatarUpload(event) {
    const file = event.target.files[0];
    if (!file) return;

    // Validate file type
    if (!file.type.startsWith('image/')) {
        alert('Vui lòng chọn file hình ảnh!');
        return;
    }

    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('Kích thước ảnh không được vượt quá 5MB!');
        return;
    }

    const formData = new FormData();
    formData.append('avatar', file);

    fetch('/upload_avatar.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            document.getElementById('profileAvatar').src = data.avatar_path;
            alert('Cập nhật avatar thành công!');
        } else {
            alert(data.message || 'Có lỗi xảy ra khi upload avatar!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra!');
    });
}

// Save basic profile changes (username, address)
function saveProfileChanges() {
    const username = document.getElementById('profileUsername').value;
    // Compose address from structured fields if present
    const countryEl = document.getElementById('profileCountry');
    const cityEl = document.getElementById('profileCity');
    const wardEl = document.getElementById('profileWard');
    const specificEl = document.getElementById('profileSpecificAddress');

    let address = '';
    if (countryEl && cityEl && wardEl && specificEl) {
        const country = (countryEl.value || '').trim();
        const city = (cityEl.value || '').trim();
        const ward = (wardEl.value || '').trim();
        const specific = (specificEl.value || '').trim();
        address = [specific, ward, city, country].filter(Boolean).join(', ');
    } else {
        const addrEl = document.getElementById('profileAddress');
        address = addrEl ? addrEl.value : '';
    }

    if (!username.trim()) {
        alert('Tên người dùng không được để trống!');
        return;
    }

    fetch('/update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update_basic',
            username: username,
            address: address
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Cập nhật thông tin thành công!');
            document.getElementById('profileUsername').readOnly = true;
            document.getElementById('profileUsername').style.backgroundColor = '#f5f5f5';
            
            // Cập nhật username display
            const usernameDisplay = document.getElementById('usernameDisplay');
            if (usernameDisplay) usernameDisplay.innerText = username;
        } else {
            alert(data.message || 'Có lỗi xảy ra!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra!');
    });
}

// Change Password Modal
function openChangePasswordModal() {
    document.getElementById('changePasswordModal').style.display = 'block';
}

function closeChangePasswordModal() {
    document.getElementById('changePasswordModal').style.display = 'none';
    document.getElementById('oldPassword').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('passwordVerifyCode').value = '';
}

function sendPasswordVerificationCode() {
    fetch('/send_verification_code.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'password' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Mã xác nhận đã được gửi đến email của bạn!');
        } else {
            alert(data.message || 'Không thể gửi mã xác nhận!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra!');
    });
}

function updatePassword() {
    const oldPassword = document.getElementById('oldPassword').value;
    const newPassword = document.getElementById('newPassword').value;
    const verifyCode = document.getElementById('passwordVerifyCode').value;

    if (!oldPassword || !newPassword || !verifyCode) {
        alert('Vui lòng điền đầy đủ thông tin!');
        return;
    }

    if (newPassword.length < 6) {
        alert('Mật khẩu mới phải có ít nhất 6 ký tự!');
        return;
    }

    fetch('/update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update_password',
            old_password: oldPassword,
            new_password: newPassword,
            verify_code: verifyCode
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Cập nhật mật khẩu thành công!');
            closeChangePasswordModal();
        } else {
            alert(data.message || 'Có lỗi xảy ra!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra!');
    });
}

// Change Phone Modal
function openChangePhoneModal() {
    document.getElementById('changePhoneModal').style.display = 'block';
}

function closeChangePhoneModal() {
    document.getElementById('changePhoneModal').style.display = 'none';
    document.getElementById('newPhone').value = '';
    document.getElementById('phoneVerifyCode').value = '';
}

function sendPhoneVerificationCode() {
    const newPhone = document.getElementById('newPhone').value;

    if (!newPhone) {
        alert('Vui lòng nhập số điện thoại mới!');
        return;
    }

    fetch('/send_verification_code.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'phone', phone: newPhone })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Mã xác nhận đã được gửi đến SĐT mới!');
        } else {
            alert(data.message || 'Không thể gửi mã xác nhận!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra!');
    });
}

function updatePhone() {
    const newPhone = document.getElementById('newPhone').value;
    const verifyCode = document.getElementById('phoneVerifyCode').value;

    if (!newPhone || !verifyCode) {
        alert('Vui lòng điền đầy đủ thông tin!');
        return;
    }

    fetch('/update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update_phone',
            phone: newPhone,
            verify_code: verifyCode
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Cập nhật SĐT thành công!');
            document.getElementById('profilePhone').value = newPhone;
            closeChangePhoneModal();
        } else {
            alert(data.message || 'Có lỗi xảy ra!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra!');
    });
}

// Change Email Modal
function openChangeEmailModal() {
    document.getElementById('changeEmailModal').style.display = 'block';
}

function closeChangeEmailModal() {
    document.getElementById('changeEmailModal').style.display = 'none';
    document.getElementById('newEmail').value = '';
    document.getElementById('emailVerifyCode').value = '';
}

function sendEmailVerificationCode() {
    const newEmail = document.getElementById('newEmail').value;

    if (!newEmail) {
        alert('Vui lòng nhập email mới!');
        return;
    }

    fetch('/send_verification_code.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type: 'email', email: newEmail })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Mã xác nhận đã được gửi đến email mới!');
        } else {
            alert(data.message || 'Không thể gửi mã xác nhận!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra!');
    });
}

function updateEmail() {
    const newEmail = document.getElementById('newEmail').value;
    const verifyCode = document.getElementById('emailVerifyCode').value;

    if (!newEmail || !verifyCode) {
        alert('Vui lòng điền đầy đủ thông tin!');
        return;
    }

    fetch('/update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'update_email',
            email: newEmail,
            verify_code: verifyCode
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert('Cập nhật email thành công!');
            document.getElementById('profileEmail').value = newEmail;
            closeChangeEmailModal();
        } else {
            alert(data.message || 'Có lỗi xảy ra!');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Có lỗi xảy ra!');
    });
}

// Close modals when clicking outside
window.addEventListener('click', function(event) {
    const profileModal = document.getElementById('profileModal');
    const changePasswordModal = document.getElementById('changePasswordModal');
    const changePhoneModal = document.getElementById('changePhoneModal');
    const changeEmailModal = document.getElementById('changeEmailModal');

    if (event.target == profileModal) {
        closeProfileModal();
    }
    if (event.target == changePasswordModal) {
        closeChangePasswordModal();
    }
    if (event.target == changePhoneModal) {
        closeChangePhoneModal();
    }
    if (event.target == changeEmailModal) {
        closeChangeEmailModal();
    }
});

// ===== Structured Address Helpers =====
async function initializeAddressFields() {
    // If already initialized, skip
    if (document.getElementById('profileCountry')) return;

    // Try to place fields near the existing address input
    const infoSection = document.querySelector('#profileModal .profile-info-section');
    if (!infoSection) throw new Error('Profile info section not found');

    // Hide legacy textarea if present
    const legacyAddress = document.getElementById('profileAddress');
    if (legacyAddress) legacyAddress.closest('.profile-field')?.classList.add('hidden');

    const container = document.createElement('div');
    container.id = 'addressFields';

    container.innerHTML = `
        <div class="profile-field">
            <label>Quốc gia:</label>
            <select id="profileCountry" class="profile-input"></select>
        </div>
        <div class="profile-field">
            <label>Thành phố/Tỉnh:</label>
            <input type="text" id="profileCity" class="profile-input" placeholder="VD: Hồ Chí Minh">
        </div>
        <div class="profile-field">
            <label>Phường/Xã/Quận/Huyện:</label>
            <input type="text" id="profileWard" class="profile-input" placeholder="VD: Phường 1, Quận 3">
        </div>
        <div class="profile-field">
            <label>Địa chỉ cụ thể:</label>
            <input type="text" id="profileSpecificAddress" class="profile-input" placeholder="VD: 123 Lý Chính Thắng">
        </div>
    `;

    // Insert before save button if possible
    const saveBtn = infoSection.querySelector('.profile-btn-primary');
    if (saveBtn) {
        infoSection.insertBefore(container, saveBtn.closest('.profile-field') || saveBtn);
    } else {
        infoSection.appendChild(container);
    }

    // Load countries list
    let countries = [];
    try {
        const res = await fetch('countries.json');
        countries = await res.json();
    } catch (e) {
        // Fallback minimal list
        countries = [
            'Việt Nam', 'United States', 'United Kingdom', 'France', 'Germany', 'Japan', 'South Korea', 'China', 'Thailand', 'Singapore', 'Malaysia', 'Indonesia', 'Philippines', 'Australia', 'Canada'
        ];
    }
    const countrySelect = document.getElementById('profileCountry');
    countries.forEach(name => {
        const opt = document.createElement('option');
        opt.value = name;
        opt.textContent = name;
        countrySelect.appendChild(opt);
    });
    // Default to Việt Nam if exists
    if (countries.includes('Việt Nam')) countrySelect.value = 'Việt Nam';
}

function populateAddressFields(addressStr) {
    // Attempt to split by comma into specific, ward, city, country
    if (!document.getElementById('profileCountry')) return;
    const parts = (addressStr || '').split(',').map(s => s.trim()).filter(Boolean);
    const countryEl = document.getElementById('profileCountry');
    const cityEl = document.getElementById('profileCity');
    const wardEl = document.getElementById('profileWard');
    const specificEl = document.getElementById('profileSpecificAddress');

    // Assign from end to beginning for flexibility
    if (parts.length >= 1) specificEl.value = parts[0] || '';
    if (parts.length >= 2) wardEl.value = parts[1] || '';
    if (parts.length >= 3) cityEl.value = parts[2] || '';
    if (parts.length >= 4) countryEl.value = parts[3] || countryEl.value;
}
