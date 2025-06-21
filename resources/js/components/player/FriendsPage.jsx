import React, { useState, useEffect } from 'react';

const FriendsPage = ({ userToken, onNavigate, onStartPrivateChat }) => {
    const [activeTab, setActiveTab] = useState('friends');
    const [friends, setFriends] = useState([]);
    const [pendingRequests, setPendingRequests] = useState([]);
    const [sentRequests, setSentRequests] = useState([]);
    const [searchResults, setSearchResults] = useState([]);
    const [searchQuery, setSearchQuery] = useState('');
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);

    useEffect(() => {
        if (activeTab === 'friends') {
            loadFriends();
        } else if (activeTab === 'requests') {
            loadPendingRequests();
            loadSentRequests();
        }
    }, [activeTab]);

    const loadFriends = async () => {
        setLoading(true);
        try {
            const response = await fetch('/api/friends', {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                },
            });
            const data = await response.json();
            if (data.status === 'success') {
                setFriends(data.data.friends);
            }
        } catch (error) {
            setError('Gagal memuat daftar teman');
        }
        setLoading(false);
    };

    const loadPendingRequests = async () => {
        try {
            const response = await fetch('/api/friends/requests/pending', {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                },
            });
            const data = await response.json();
            if (data.status === 'success') {
                setPendingRequests(data.data.requests.data);
            }
        } catch (error) {
            console.error('Error loading pending requests:', error);
        }
    };

    const loadSentRequests = async () => {
        try {
            const response = await fetch('/api/friends/requests/sent', {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                },
            });
            const data = await response.json();
            if (data.status === 'success') {
                setSentRequests(data.data.requests.data);
            }
        } catch (error) {
            console.error('Error loading sent requests:', error);
        }
    };

    const searchUsers = async (query) => {
        if (query.length < 2) {
            setSearchResults([]);
            return;
        }
        
        setLoading(true);
        try {
            const response = await fetch(`/api/friends/search?query=${encodeURIComponent(query)}`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                },
            });
            const data = await response.json();
            if (data.status === 'success') {
                setSearchResults(data.data.users.data);
            }
        } catch (error) {
            setError('Gagal mencari pengguna');
        }
        setLoading(false);
    };

    const sendFriendRequest = async (userId, message = '') => {
        try {
            const response = await fetch('/api/friends/request', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_id: userId,
                    message: message
                }),
            });
            const data = await response.json();
            if (data.status === 'success') {
                setSuccess('Permintaan pertemanan berhasil dikirim!');
                // Update search results to reflect new status
                searchUsers(searchQuery);
            } else {
                setError(data.message);
            }
        } catch (error) {
            setError('Gagal mengirim permintaan pertemanan');
        }
    };

    const acceptFriendRequest = async (requestId) => {
        try {
            const response = await fetch(`/api/friends/requests/${requestId}/accept`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                },
            });
            const data = await response.json();
            if (data.status === 'success') {
                setSuccess('Permintaan pertemanan diterima!');
                loadPendingRequests();
                if (activeTab === 'friends') {
                    loadFriends();
                }
            } else {
                setError(data.message);
            }
        } catch (error) {
            setError('Gagal menerima permintaan pertemanan');
        }
    };

    const rejectFriendRequest = async (requestId) => {
        try {
            const response = await fetch(`/api/friends/requests/${requestId}/reject`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                },
            });
            const data = await response.json();
            if (data.status === 'success') {
                setSuccess('Permintaan pertemanan ditolak');
                loadPendingRequests();
            } else {
                setError(data.message);
            }
        } catch (error) {
            setError('Gagal menolak permintaan pertemanan');
        }
    };

    const removeFriend = async (friendId) => {
        if (!confirm('Apakah Anda yakin ingin menghapus teman ini?')) {
            return;
        }
        
        try {
            const response = await fetch(`/api/friends/${friendId}`, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                },
            });
            const data = await response.json();
            if (data.status === 'success') {
                setSuccess('Teman berhasil dihapus');
                loadFriends();
            } else {
                setError(data.message);
            }
        } catch (error) {
            setError('Gagal menghapus teman');
        }
    };

    const handleSearchChange = (e) => {
        const query = e.target.value;
        setSearchQuery(query);
        searchUsers(query);
    };

    const formatDate = (dateString) => {
        if (!dateString) return '';
        return new Date(dateString).toLocaleDateString('id-ID', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };

    const clearMessages = () => {
        setError(null);
        setSuccess(null);
    };

    const FriendCard = ({ friend, onRemove }) => (
        <div className="bg-white rounded-lg shadow-md p-4 hover:shadow-lg transition-shadow">
            <div className="flex items-center space-x-4">
                <div className="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                    {friend.name.charAt(0).toUpperCase()}
                </div>
                <div className="flex-1">
                    <h3 className="font-semibold text-gray-900">{friend.name}</h3>
                    <p className="text-sm text-gray-600">{friend.email}</p>
                    {friend.subscription_tier === 'premium' && (
                        <span className="inline-block bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full mt-1">
                            ⭐ Premium
                        </span>
                    )}
                </div>
                <div className="text-right">
                    <p className="text-xs text-gray-500">
                        Berteman sejak: {formatDate(friend.friendship_date)}
                    </p>
                    <p className="text-xs text-gray-500">
                        {friend.mutual_friends_count} teman bersama
                    </p>
                    <div className="flex space-x-2 mt-2">
                        <button
                            onClick={() => onStartPrivateChat && onStartPrivateChat(friend.id)}
                            className="bg-primary text-white px-3 py-1 rounded text-sm hover:bg-primary-dark transition-colors"
                        >
                            Chat
                        </button>
                        <button
                            onClick={() => onRemove(friend.id)}
                            className="text-red-600 hover:text-red-800 text-sm px-2 py-1"
                        >
                            Hapus
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );

    const RequestCard = ({ request, type, onAccept, onReject }) => (
        <div className="bg-white rounded-lg shadow-md p-4 hover:shadow-lg transition-shadow">
            <div className="flex items-center space-x-4">
                <div className="w-12 h-12 bg-green-500 rounded-full flex items-center justify-center text-white font-bold">
                    {(type === 'received' ? request.sender.name : request.receiver.name).charAt(0).toUpperCase()}
                </div>
                <div className="flex-1">
                    <h3 className="font-semibold text-gray-900">
                        {type === 'received' ? request.sender.name : request.receiver.name}
                    </h3>
                    <p className="text-sm text-gray-600">
                        {type === 'received' ? request.sender.email : request.receiver.email}
                    </p>
                    {request.message && (
                        <p className="text-sm text-gray-700 mt-1 italic">"{request.message}"</p>
                    )}
                    <p className="text-xs text-gray-500 mt-1">
                        {formatDate(request.created_at)}
                    </p>
                </div>
                <div className="flex space-x-2">
                    {type === 'received' ? (
                        <>
                            <button
                                onClick={() => onAccept(request.id)}
                                className="bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700"
                            >
                                Terima
                            </button>
                            <button
                                onClick={() => onReject(request.id)}
                                className="bg-red-600 text-white px-3 py-1 rounded text-sm hover:bg-red-700"
                            >
                                Tolak
                            </button>
                        </>
                    ) : (
                        <span className="text-sm text-gray-500 px-3 py-1 bg-gray-100 rounded">
                            Menunggu respon
                        </span>
                    )}
                </div>
            </div>
        </div>
    );

    const SearchUserCard = ({ user, onSendRequest }) => (
        <div className="bg-white rounded-lg shadow-md p-4 hover:shadow-lg transition-shadow">
            <div className="flex items-center space-x-4">
                <div className="w-12 h-12 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold">
                    {user.name.charAt(0).toUpperCase()}
                </div>
                <div className="flex-1">
                    <h3 className="font-semibold text-gray-900">{user.name}</h3>
                    <p className="text-sm text-gray-600">{user.email}</p>
                    {user.subscription_tier === 'premium' && (
                        <span className="inline-block bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full mt-1">
                            ⭐ Premium
                        </span>
                    )}
                </div>
                <div>
                    {user.is_friends ? (
                        <span className="text-green-600 text-sm">✓ Sudah berteman</span>
                    ) : user.request_status ? (
                        <span className="text-gray-500 text-sm">
                            {user.request_status.type === 'sent' ? 'Permintaan terkirim' : 'Permintaan diterima'}
                        </span>
                    ) : user.can_send_request ? (
                        <button
                            onClick={() => onSendRequest(user.id)}
                            className="bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700"
                        >
                            Kirim Permintaan
                        </button>
                    ) : (
                        <span className="text-gray-400 text-sm">Tidak dapat mengirim</span>
                    )}
                </div>
            </div>
        </div>
    );

    return (
        <div className="min-h-screen bg-gray-50 pb-20">
            {/* Header */}
            <div className="bg-white shadow-sm">
                <div className="max-w-md mx-auto px-4 py-4">
                    <h1 className="text-xl font-bold text-gray-900">Teman</h1>
                </div>
            </div>

            {/* Messages */}
            {(error || success) && (
                <div className="max-w-md mx-auto px-4 mt-4">
                    {error && (
                        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                            {error}
                            <button onClick={clearMessages} className="float-right text-red-500 hover:text-red-700">×</button>
                        </div>
                    )}
                    {success && (
                        <div className="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                            {success}
                            <button onClick={clearMessages} className="float-right text-green-500 hover:text-green-700">×</button>
                        </div>
                    )}
                </div>
            )}

            {/* Tab Navigation */}
            <div className="max-w-md mx-auto px-4 mt-4">
                <div className="flex bg-gray-100 rounded-lg p-1">
                    <button
                        onClick={() => setActiveTab('friends')}
                        className={`flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors ${
                            activeTab === 'friends'
                                ? 'bg-white text-blue-600 shadow-sm'
                                : 'text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        Teman ({friends.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('requests')}
                        className={`flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors ${
                            activeTab === 'requests'
                                ? 'bg-white text-blue-600 shadow-sm'
                                : 'text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        Permintaan ({pendingRequests.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('search')}
                        className={`flex-1 py-2 px-4 rounded-md text-sm font-medium transition-colors ${
                            activeTab === 'search'
                                ? 'bg-white text-blue-600 shadow-sm'
                                : 'text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        Cari Teman
                    </button>
                </div>
            </div>

            {/* Content */}
            <div className="max-w-md mx-auto px-4 mt-6">
                {activeTab === 'friends' && (
                    <div className="space-y-4">
                        {loading ? (
                            <div className="text-center py-8">
                                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div>
                                <p className="text-gray-600 mt-2">Memuat daftar teman...</p>
                            </div>
                        ) : friends.length === 0 ? (
                            <div className="text-center py-8">
                                <p className="text-gray-600">Belum ada teman</p>
                                <button
                                    onClick={() => setActiveTab('search')}
                                    className="mt-2 text-blue-600 hover:text-blue-800"
                                >
                                    Cari teman baru
                                </button>
                            </div>
                        ) : (
                            friends.map(friend => (
                                <FriendCard
                                    key={friend.id}
                                    friend={friend}
                                    onRemove={removeFriend}
                                />
                            ))
                        )}
                    </div>
                )}

                {activeTab === 'requests' && (
                    <div className="space-y-6">
                        {/* Pending Requests */}
                        <div>
                            <h2 className="text-lg font-semibold text-gray-900 mb-3">
                                Permintaan Masuk ({pendingRequests.length})
                            </h2>
                            <div className="space-y-4">
                                {pendingRequests.length === 0 ? (
                                    <p className="text-gray-600 text-center py-4">Tidak ada permintaan masuk</p>
                                ) : (
                                    pendingRequests.map(request => (
                                        <RequestCard
                                            key={request.id}
                                            request={request}
                                            type="received"
                                            onAccept={acceptFriendRequest}
                                            onReject={rejectFriendRequest}
                                        />
                                    ))
                                )}
                            </div>
                        </div>

                        {/* Sent Requests */}
                        <div>
                            <h2 className="text-lg font-semibold text-gray-900 mb-3">
                                Permintaan Terkirim ({sentRequests.length})
                            </h2>
                            <div className="space-y-4">
                                {sentRequests.length === 0 ? (
                                    <p className="text-gray-600 text-center py-4">Tidak ada permintaan terkirim</p>
                                ) : (
                                    sentRequests.map(request => (
                                        <RequestCard
                                            key={request.id}
                                            request={request}
                                            type="sent"
                                        />
                                    ))
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {activeTab === 'search' && (
                    <div className="space-y-4">
                        {/* Search Input */}
                        <div className="relative">
                            <input
                                type="text"
                                placeholder="Cari berdasarkan nama atau email..."
                                value={searchQuery}
                                onChange={handleSearchChange}
                                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                            {loading && (
                                <div className="absolute right-3 top-3">
                                    <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                                </div>
                            )}
                        </div>

                        {/* Search Results */}
                        <div className="space-y-4">
                            {searchQuery.length < 2 ? (
                                <p className="text-gray-600 text-center py-4">
                                    Masukkan minimal 2 karakter untuk mencari
                                </p>
                            ) : searchResults.length === 0 && !loading ? (
                                <p className="text-gray-600 text-center py-4">
                                    Tidak ada pengguna ditemukan
                                </p>
                            ) : (
                                searchResults.map(user => (
                                    <SearchUserCard
                                        key={user.id}
                                        user={user}
                                        onSendRequest={sendFriendRequest}
                                    />
                                ))
                            )}
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default FriendsPage; 