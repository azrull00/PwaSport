import React, { useState, useEffect } from 'react';

const ProfilePage = ({ user, userToken, onLogout, onUserUpdate, onNavigate }) => {
    const [isEditing, setIsEditing] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [activeTab, setActiveTab] = useState('profile'); // profile, stats, settings
    const [formData, setFormData] = useState({
        first_name: '',
        last_name: '',
        phone_number: '',
        date_of_birth: '',
        gender: '',
        city: '',
        province: '',
        country: ''
    });
    const [stats, setStats] = useState(null);
    const [sportRatings, setSportRatings] = useState([]);
    const [creditScoreHistory, setCreditScoreHistory] = useState([]);
    const [profilePicture, setProfilePicture] = useState(null);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    useEffect(() => {
        if (user) {
            setFormData({
                first_name: user.profile?.first_name || '',
                last_name: user.profile?.last_name || '',
                phone_number: user.phone_number || '',
                date_of_birth: user.profile?.date_of_birth || '',
                gender: user.profile?.gender || '',
                city: user.profile?.city || '',
                province: user.profile?.province || '',
                country: user.profile?.country || 'Indonesia'
            });
        }
        
        if (activeTab === 'stats') {
            loadUserStats();
        }
    }, [user, activeTab]);

    const loadUserStats = async () => {
        try {
            // Load sport ratings
            const ratingsResponse = await fetch(`/api/users/${user.id}/sport-ratings`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });
            
            if (ratingsResponse.ok) {
                const ratingsData = await ratingsResponse.json();
                if (ratingsData.status === 'success') {
                    setSportRatings(ratingsData.data.sport_ratings || []);
                }
            }

            // Load credit score history
            const creditResponse = await fetch('/api/credit-score', {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });
            
            if (creditResponse.ok) {
                const creditData = await creditResponse.json();
                if (creditData.status === 'success') {
                    setCreditScoreHistory(creditData.data.logs || []);
                }
            }

        } catch (error) {
            console.error('Error loading user stats:', error);
        }
    };

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({
            ...prev,
            [name]: value
        }));
    };

    const handleSaveProfile = async () => {
        setIsLoading(true);
        setError('');
        setSuccess('');

        try {
            const response = await fetch('/api/auth/profile', {
                method: 'PUT',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                setSuccess('Profil berhasil diperbarui!');
                setIsEditing(false);
                onUserUpdate(data.data.user);
                setTimeout(() => setSuccess(''), 3000);
            } else {
                setError(data.message || 'Gagal memperbarui profil');
            }
        } catch (error) {
            console.error('Error updating profile:', error);
            setError('Terjadi kesalahan saat memperbarui profil');
        } finally {
            setIsLoading(false);
        }
    };

    const handleProfilePictureUpload = async (e) => {
        const file = e.target.files[0];
        if (!file) return;

        setIsLoading(true);
        setError('');

        const formDataPicture = new FormData();
        formDataPicture.append('profile_picture', file);

        try {
            const response = await fetch('/api/users/upload-profile-picture', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                },
                body: formDataPicture
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                setSuccess('Foto profil berhasil diperbarui!');
                onUserUpdate(data.data.user);
                setTimeout(() => setSuccess(''), 3000);
            } else {
                setError(data.message || 'Gagal mengupload foto profil');
            }
        } catch (error) {
            console.error('Error uploading profile picture:', error);
            setError('Terjadi kesalahan saat mengupload foto profil');
        } finally {
            setIsLoading(false);
        }
    };

    const handleDeleteProfilePicture = async () => {
        if (!confirm('Apakah Anda yakin ingin menghapus foto profil?')) return;

        setIsLoading(true);
        setError('');

        try {
            const response = await fetch('/api/users/delete-profile-picture', {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                setSuccess('Foto profil berhasil dihapus!');
                onUserUpdate(data.data.user);
                setTimeout(() => setSuccess(''), 3000);
            } else {
                setError(data.message || 'Gagal menghapus foto profil');
            }
        } catch (error) {
            console.error('Error deleting profile picture:', error);
            setError('Terjadi kesalahan saat menghapus foto profil');
        } finally {
            setIsLoading(false);
        }
    };

    const ProfileTab = () => (
        <div className="space-y-6">
            {/* Profile Picture Section */}
            <div className="bg-white rounded-xl p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900 mb-4">Foto Profil</h3>
                <div className="flex items-center space-x-4">
                    <div className="relative">
                        {user?.profile?.profile_photo_url ? (
                            <img
                                src={`/storage/${user.profile.profile_photo_url}`}
                                alt="Profile"
                                className="w-20 h-20 rounded-full object-cover border-4 border-primary"
                            />
                        ) : (
                            <div className="w-20 h-20 bg-primary rounded-full flex items-center justify-center">
                                <span className="text-white text-2xl font-bold">
                                    {(user?.profile?.first_name || user?.name || 'U').charAt(0)}
                                </span>
                            </div>
                        )}
                    </div>
                    <div className="flex-1">
                        <div className="flex space-x-2">
                            <label className="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium cursor-pointer hover:bg-primary-dark transition-colors">
                                Upload Foto
                                <input
                                    type="file"
                                    accept="image/*"
                                    onChange={handleProfilePictureUpload}
                                    className="hidden"
                                />
                            </label>
                            {user?.profile?.profile_photo_url && (
                                <button
                                    onClick={handleDeleteProfilePicture}
                                    className="bg-red-100 text-red-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-200 transition-colors"
                                >
                                    Hapus
                                </button>
                            )}
                        </div>
                        <p className="text-xs text-gray-500 mt-1">
                            Format: JPG, PNG, WebP. Maksimal 2MB.
                        </p>
                    </div>
                </div>
            </div>

            {/* Basic Information */}
            <div className="bg-white rounded-xl p-6 shadow-sm">
                <div className="flex justify-between items-center mb-4">
                    <h3 className="font-semibold text-gray-900">Informasi Dasar</h3>
                    <button
                        onClick={() => setIsEditing(!isEditing)}
                        className="text-primary text-sm font-medium hover:underline"
                    >
                        {isEditing ? 'Batal' : 'Edit'}
                    </button>
                </div>

                {isEditing ? (
                    <div className="space-y-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Nama Depan
                                </label>
                                <input
                                    type="text"
                                    name="first_name"
                                    value={formData.first_name}
                                    onChange={handleInputChange}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Nama Belakang
                                </label>
                                <input
                                    type="text"
                                    name="last_name"
                                    value={formData.last_name}
                                    onChange={handleInputChange}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Nomor HP
                            </label>
                            <input
                                type="text"
                                name="phone_number"
                                value={formData.phone_number}
                                onChange={handleInputChange}
                                className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                            />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Tanggal Lahir
                                </label>
                                <input
                                    type="date"
                                    name="date_of_birth"
                                    value={formData.date_of_birth}
                                    onChange={handleInputChange}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Jenis Kelamin
                                </label>
                                <select
                                    name="gender"
                                    value={formData.gender}
                                    onChange={handleInputChange}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                >
                                    <option value="">Pilih...</option>
                                    <option value="male">Laki-laki</option>
                                    <option value="female">Perempuan</option>
                                </select>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Kota
                                </label>
                                <input
                                    type="text"
                                    name="city"
                                    value={formData.city}
                                    onChange={handleInputChange}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    Provinsi
                                </label>
                                <input
                                    type="text"
                                    name="province"
                                    value={formData.province}
                                    onChange={handleInputChange}
                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                />
                            </div>
                        </div>

                        <div className="flex space-x-3 pt-4">
                            <button
                                onClick={handleSaveProfile}
                                disabled={isLoading}
                                className="bg-primary text-white px-6 py-2 rounded-lg font-medium hover:bg-primary-dark transition-colors disabled:opacity-50"
                            >
                                {isLoading ? 'Menyimpan...' : 'Simpan'}
                            </button>
                            <button
                                onClick={() => setIsEditing(false)}
                                className="bg-gray-100 text-gray-700 px-6 py-2 rounded-lg font-medium hover:bg-gray-200 transition-colors"
                            >
                                Batal
                            </button>
                        </div>
                    </div>
                ) : (
                    <div className="space-y-3">
                        <div className="flex justify-between">
                            <span className="text-gray-600">Nama Lengkap</span>
                            <span className="font-medium">
                                {user?.profile?.first_name && user?.profile?.last_name 
                                    ? `${user.profile.first_name} ${user.profile.last_name}`
                                    : user?.name || '-'
                                }
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-600">Email</span>
                            <span className="font-medium">{user?.email || '-'}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-600">Nomor HP</span>
                            <span className="font-medium">{user?.phone_number || '-'}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-600">Tanggal Lahir</span>
                            <span className="font-medium">
                                {user?.profile?.date_of_birth 
                                    ? new Date(user.profile.date_of_birth).toLocaleDateString('id-ID')
                                    : '-'
                                }
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-600">Jenis Kelamin</span>
                            <span className="font-medium">
                                {user?.profile?.gender === 'male' ? 'Laki-laki' :
                                 user?.profile?.gender === 'female' ? 'Perempuan' : '-'}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-gray-600">Lokasi</span>
                            <span className="font-medium">
                                {user?.profile?.city && user?.profile?.province
                                    ? `${user.profile.city}, ${user.profile.province}`
                                    : user?.profile?.city || '-'
                                }
                            </span>
                        </div>
                    </div>
                )}
            </div>

            {/* Quick Actions */}
            <div className="bg-white rounded-xl p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900 mb-4">Akses Cepat</h3>
                <div className="grid grid-cols-2 gap-3">
                    <button 
                        onClick={() => onNavigate('matchmakingStatus')}
                        className="bg-white border-2 border-primary p-4 rounded-xl text-center hover:bg-primary transition-colors group"
                    >
                        <div className="text-2xl mb-2">🎯</div>
                        <div className="text-sm font-medium text-primary group-hover:text-white">Status Matchmaking</div>
                        <div className="text-xs text-gray-600 group-hover:text-gray-100 mt-1">Lihat status pertandingan</div>
                    </button>
                    <button 
                        onClick={() => onNavigate('matchHistory')}
                        className="bg-white border-2 border-green-500 p-4 rounded-xl text-center hover:bg-green-500 transition-colors group"
                    >
                        <div className="text-2xl mb-2">📋</div>
                        <div className="text-sm font-medium text-green-600 group-hover:text-white">Riwayat Match</div>
                        <div className="text-xs text-gray-600 group-hover:text-gray-100 mt-1">Lihat statistik Anda</div>
                    </button>
                </div>
            </div>

            {/* Account Info */}
            <div className="bg-white rounded-xl p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900 mb-4">Informasi Akun</h3>
                <div className="space-y-3">
                    <div className="flex justify-between">
                        <span className="text-gray-600">Tipe User</span>
                        <span className="font-medium capitalize">
                            {user?.user_type || 'Player'}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-gray-600">Credit Score</span>
                        <span className="font-bold text-primary text-lg">
                            {user?.credit_score || 100}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-gray-600">Status Akun</span>
                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${
                            user?.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
                        }`}>
                            {user?.is_active ? 'Aktif' : 'Tidak Aktif'}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-gray-600">Bergabung</span>
                        <span className="font-medium">
                            {user?.created_at 
                                ? new Date(user.created_at).toLocaleDateString('id-ID', {
                                    day: 'numeric',
                                    month: 'long',
                                    year: 'numeric'
                                })
                                : '-'
                            }
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );

    const StatsTab = () => (
        <div className="space-y-6">
            {/* Credit Score History */}
            <div className="bg-white rounded-xl p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900 mb-4">Riwayat Credit Score</h3>
                {creditScoreHistory.length > 0 ? (
                    <div className="space-y-3">
                        {creditScoreHistory.slice(0, 10).map((log, index) => (
                            <div key={index} className="flex justify-between items-center py-2 border-b border-gray-100 last:border-b-0">
                                <div className="flex-1">
                                    <p className="font-medium text-sm">{log.description}</p>
                                    <p className="text-xs text-gray-500">
                                        {new Date(log.created_at).toLocaleDateString('id-ID')}
                                    </p>
                                </div>
                                <div className={`font-bold text-sm ${
                                    log.amount > 0 ? 'text-green-600' : 'text-red-600'
                                }`}>
                                    {log.amount > 0 ? '+' : ''}{log.amount}
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-gray-500 text-center py-4">Belum ada riwayat credit score</p>
                )}
            </div>

            {/* Sport Ratings */}
            <div className="bg-white rounded-xl p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900 mb-4">Rating Olahraga</h3>
                {sportRatings.length > 0 ? (
                    <div className="space-y-3">
                        {sportRatings.map((rating) => (
                            <div key={rating.id} className="flex justify-between items-center">
                                <span className="font-medium">{rating.sport?.name}</span>
                                <div className="flex items-center space-x-2">
                                    <span className="text-primary font-bold">{rating.skill_level}</span>
                                    <span className="text-gray-500">•</span>
                                    <span className="text-yellow-500">★ {rating.average_rating || 'N/A'}</span>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="text-gray-500 text-center py-4">Belum ada rating olahraga</p>
                )}
            </div>
        </div>
    );

    const SettingsTab = () => (
        <div className="space-y-6">
            {/* Logout Section */}
            <div className="bg-white rounded-xl p-6 shadow-sm">
                <h3 className="font-semibold text-gray-900 mb-4">Keluar Akun</h3>
                <p className="text-gray-600 mb-4">
                    Keluar dari akun Anda dan kembali ke halaman login.
                </p>
                <button 
                    onClick={onLogout}
                    className="w-full bg-red-500 text-white py-3 rounded-xl font-semibold hover:bg-red-600 transition-colors"
                >
                    Keluar
                </button>
            </div>

            {/* Danger Zone */}
            <div className="bg-red-50 rounded-xl p-6 border border-red-200">
                <h3 className="font-semibold text-red-900 mb-4">Zona Berbahaya</h3>
                <p className="text-red-700 text-sm mb-4">
                    Tindakan ini tidak dapat dibatalkan. Pastikan Anda memahami konsekuensinya.
                </p>
                <button className="bg-red-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-red-700 transition-colors">
                    Hapus Akun
                </button>
            </div>
        </div>
    );

    return (
        <div className="bg-secondary min-h-screen">
            {/* Header */}
            <div className="bg-white border-b border-gray-100 px-4 py-3">
                <h1 className="text-lg font-semibold text-gray-900">Profil</h1>
            </div>

            {/* Alerts */}
            {error && (
                <div className="mx-4 mt-4 bg-red-50 border border-red-200 rounded-xl p-4">
                    <p className="text-red-600 text-sm">{error}</p>
                </div>
            )}
            
            {success && (
                <div className="mx-4 mt-4 bg-green-50 border border-green-200 rounded-xl p-4">
                    <p className="text-green-600 text-sm">{success}</p>
                </div>
            )}

            {/* Tabs */}
            <div className="p-4">
                <div className="flex bg-gray-100 rounded-xl p-1">
                    <button
                        onClick={() => setActiveTab('profile')}
                        className={`flex-1 py-2 px-4 rounded-lg font-medium transition-colors ${
                            activeTab === 'profile'
                                ? 'bg-white text-primary shadow-sm'
                                : 'text-gray-600 hover:text-gray-800'
                        }`}
                    >
                        Profil
                    </button>
                    <button
                        onClick={() => setActiveTab('stats')}
                        className={`flex-1 py-2 px-4 rounded-lg font-medium transition-colors ${
                            activeTab === 'stats'
                                ? 'bg-white text-primary shadow-sm'
                                : 'text-gray-600 hover:text-gray-800'
                        }`}
                    >
                        Statistik
                    </button>
                    <button
                        onClick={() => setActiveTab('settings')}
                        className={`flex-1 py-2 px-4 rounded-lg font-medium transition-colors ${
                            activeTab === 'settings'
                                ? 'bg-white text-primary shadow-sm'
                                : 'text-gray-600 hover:text-gray-800'
                        }`}
                    >
                        Pengaturan
                    </button>
                </div>
            </div>

            {/* Content */}
            <div className="px-4 pb-20">
                {activeTab === 'profile' && <ProfileTab />}
                {activeTab === 'stats' && <StatsTab />}
                {activeTab === 'settings' && <SettingsTab />}
            </div>
        </div>
    );
};

export default ProfilePage; 