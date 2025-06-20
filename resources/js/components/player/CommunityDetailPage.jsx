import React, { useState, useEffect } from 'react';

// Temporary inline function to avoid import issues
const getUserIdFromToken = (token) => {
    try {
        if (!token || typeof token !== 'string') {
            return null;
        }
        const tokenParts = token.split('.');
        if (tokenParts.length !== 3) {
            return null;
        }
        const payload = JSON.parse(atob(tokenParts[1]));
        return payload.sub;
    } catch (error) {
        console.error('Error parsing JWT token:', error);
        return null;
    }
};

const CommunityDetailPage = ({ communityId, userToken, onNavigate, onBack }) => {
    const [community, setCommunity] = useState(null);
    const [members, setMembers] = useState([]);
    const [userMembership, setUserMembership] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [isJoining, setIsJoining] = useState(false);
    const [isLeaving, setIsLeaving] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (communityId && userToken) {
            loadCommunityDetails();
            loadMembers();
        }
    }, [communityId, userToken]);

    const loadCommunityDetails = async () => {
        try {
            const response = await fetch(`/api/communities/${communityId}`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (data.status === 'success') {
                setCommunity(data.data.community);
                
                // Check if user is already a member by loading members separately
                const userId = getUserIdFromToken(userToken);
                if (userId) {
                    // We'll check membership status when loading members
                    setUserMembership(null);
                } else {
                    setUserMembership(null);
                }
            } else {
                setError('Gagal memuat detail komunitas');
            }
        } catch (error) {
            console.error('Error loading community details:', error);
            setError('Terjadi kesalahan saat memuat komunitas');
        }
    };

    const loadMembers = async () => {
        try {
            const response = await fetch(`/api/communities/${communityId}/members`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (data.status === 'success') {
                const members = data.data.members || [];
                setMembers(members);
                
                // Check if current user is a member
                const userId = getUserIdFromToken(userToken);
                if (userId) {
                    const userMember = members.find(m => m.user_id === userId);
                    setUserMembership(userMember || null);
                }
            }
        } catch (error) {
            console.error('Error loading members:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const handleJoinCommunity = async () => {
        if (!community || isJoining) return;

        setIsJoining(true);
        setError('');

        try {
            const response = await fetch(`/api/communities/${communityId}/join`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            console.log('Join community response:', data);
            
            if (data.status === 'success') {
                // Reload community details and members
                await loadCommunityDetails();
                await loadMembers();
            } else {
                console.error('Join community error:', data);
                setError(data.message || 'Gagal bergabung dengan komunitas');
            }
        } catch (error) {
            console.error('Error joining community:', error);
            setError('Terjadi kesalahan saat bergabung dengan komunitas');
        } finally {
            setIsJoining(false);
        }
    };

    const handleLeaveCommunity = async () => {
        if (!community || !userMembership || isLeaving) return;

        if (!confirm('Apakah Anda yakin ingin keluar dari komunitas ini?')) return;

        setIsLeaving(true);
        setError('');

        try {
            const response = await fetch(`/api/communities/${communityId}/leave`, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (data.status === 'success') {
                // Reload community details and members
                await loadCommunityDetails();
                await loadMembers();
            } else {
                setError(data.message || 'Gagal keluar dari komunitas');
            }
        } catch (error) {
            console.error('Error leaving community:', error);
            setError('Terjadi kesalahan saat keluar dari komunitas');
        } finally {
            setIsLeaving(false);
        }
    };

    const getMembershipStatus = () => {
        if (!userMembership) return null;
        
        switch (userMembership.status) {
            case 'active':
                return { text: 'Anggota Aktif', color: 'bg-green-100 text-green-700' };
            case 'pending':
                return { text: 'Menunggu Persetujuan', color: 'bg-yellow-100 text-yellow-700' };
            case 'banned':
                return { text: 'Diblokir', color: 'bg-red-100 text-red-700' };
            default:
                return { text: 'Status Tidak Diketahui', color: 'bg-gray-100 text-gray-700' };
        }
    };

    const getCommunityTypeDisplay = (type) => {
        switch (type) {
            case 'public':
                return { text: 'Publik', color: 'bg-green-100 text-green-700', icon: '🌍' };
            case 'private':
                return { text: 'Privat', color: 'bg-blue-100 text-blue-700', icon: '🔒' };
            case 'invite_only':
                return { text: 'Undangan', color: 'bg-orange-100 text-orange-700', icon: '✉️' };
            default:
                return { text: 'Tidak Diketahui', color: 'bg-gray-100 text-gray-700', icon: '❓' };
        }
    };

    if (isLoading) {
        return (
            <div className="bg-secondary min-h-screen">
                <div className="flex items-center justify-center h-64">
                    <div className="text-center">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                        <p className="text-gray-600 mt-2">Memuat detail komunitas...</p>
                    </div>
                </div>
            </div>
        );
    }

    if (!community) {
        return (
            <div className="bg-secondary min-h-screen">
                <div className="flex items-center justify-center h-64">
                    <div className="text-center">
                        <p className="text-gray-600">Komunitas tidak ditemukan</p>
                        <button 
                            onClick={onBack}
                            className="mt-4 bg-primary text-white px-4 py-2 rounded-lg"
                        >
                            Kembali
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    const membershipStatus = getMembershipStatus();
    const communityType = getCommunityTypeDisplay(community.community_type);

    return (
        <div className="bg-secondary min-h-screen">
            {/* Header */}
            <div className="bg-white border-b border-gray-100 px-4 py-3">
                <div className="flex items-center">
                    <button 
                        onClick={onBack}
                        className="mr-3 p-2 rounded-lg hover:bg-gray-100"
                    >
                        ←
                    </button>
                    <h1 className="text-lg font-semibold text-gray-900">Detail Komunitas</h1>
                </div>
            </div>

            <div className="p-4">
                {/* Error Message */}
                {error && (
                    <div className="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                        <p className="text-red-600 text-sm">{error}</p>
                    </div>
                )}

                {/* Community Header */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
                    <div className="flex items-start space-x-4 mb-4">
                        <div className="w-16 h-16 bg-primary rounded-full flex items-center justify-center flex-shrink-0">
                            <span className="text-white font-bold text-xl">{community.name.charAt(0)}</span>
                        </div>
                        
                        <div className="flex-1 min-w-0">
                            <h1 className="text-xl font-bold text-gray-900 mb-2">{community.name}</h1>
                            
                            <div className="flex items-center space-x-2 mb-2">
                                <span className={`px-2 py-1 rounded-full text-xs font-medium ${communityType.color}`}>
                                    {communityType.icon} {communityType.text}
                                </span>
                                
                                {membershipStatus && (
                                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${membershipStatus.color}`}>
                                        {membershipStatus.text}
                                    </span>
                                )}
                            </div>

                            <div className="flex items-center text-sm text-gray-600 space-x-4">
                                <span className="flex items-center">
                                    <span className="mr-1">🏸</span>
                                    {community.sport?.name || 'Sport'}
                                </span>
                                <span className="flex items-center">
                                    <span className="mr-1">👥</span>
                                    {community.member_count || members.length} anggota
                                </span>
                                <span className="flex items-center">
                                    <span className="mr-1">📍</span>
                                    {community.venue_city || community.city || 'Kota tidak disebutkan'}
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Description */}
                    {community.description && (
                        <div className="mb-4">
                            <h3 className="font-semibold text-gray-900 mb-2">Deskripsi</h3>
                            <p className="text-gray-600 text-sm leading-relaxed">{community.description}</p>
                        </div>
                    )}

                    {/* Community Info */}
                    <div className="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <span className="text-xs font-medium text-gray-500">Level Focus</span>
                            <p className="font-medium text-gray-900">
                                {community.skill_level_focus || 'Mixed'}
                            </p>
                        </div>
                        <div>
                            <span className="text-xs font-medium text-gray-500">Biaya Keanggotaan</span>
                            <p className="font-medium text-gray-900">
                                {community.membership_fee ? `Rp ${community.membership_fee.toLocaleString()}/bulan` : 'Gratis'}
                            </p>
                        </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="flex space-x-3">
                        {!userMembership ? (
                            <button
                                onClick={handleJoinCommunity}
                                disabled={isJoining}
                                className="flex-1 bg-primary text-white py-3 px-4 rounded-lg font-medium hover:bg-primary-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {isJoining ? 'Bergabung...' : 'Bergabung'}
                            </button>
                        ) : (
                            <button
                                onClick={handleLeaveCommunity}
                                disabled={isLeaving}
                                className="flex-1 bg-red-500 text-white py-3 px-4 rounded-lg font-medium hover:bg-red-600 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {isLeaving ? 'Keluar...' : 'Keluar dari Komunitas'}
                            </button>
                        )}
                        
                        <button 
                            onClick={() => onNavigate('chat')}
                            className="px-6 py-3 bg-gray-100 text-gray-700 rounded-lg font-medium hover:bg-gray-200 transition-colors"
                        >
                            💬 Chat
                        </button>
                    </div>
                </div>

                {/* Members Section */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-semibold text-gray-900">
                            Anggota ({members.length})
                        </h2>
                    </div>

                    {members.length > 0 ? (
                        <div className="space-y-3">
                            {members.map((member) => (
                                <div key={member.id} className="flex items-center space-x-3 p-3 rounded-lg hover:bg-gray-50">
                                    <div className="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
                                        <span className="text-white font-medium text-sm">
                                            {member.user?.profile?.first_name?.charAt(0) || member.user?.name?.charAt(0) || '?'}
                                        </span>
                                    </div>
                                    
                                    <div className="flex-1">
                                        <h4 className="font-medium text-gray-900">
                                            {member.user?.profile?.first_name 
                                                ? `${member.user.profile.first_name} ${member.user.profile.last_name || ''}`.trim()
                                                : member.user?.name || 'User'}
                                        </h4>
                                        <p className="text-sm text-gray-600">
                                            Bergabung {new Date(member.joined_at).toLocaleDateString('id-ID')}
                                        </p>
                                    </div>

                                    <div className="flex items-center space-x-2">
                                        {member.role === 'admin' && (
                                            <span className="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs font-medium rounded-full">
                                                Admin
                                            </span>
                                        )}
                                        {member.role === 'moderator' && (
                                            <span className="px-2 py-1 bg-blue-100 text-blue-700 text-xs font-medium rounded-full">
                                                Moderator
                                            </span>
                                        )}
                                        <span className={`px-2 py-1 text-xs font-medium rounded-full ${
                                            member.status === 'active' ? 'bg-green-100 text-green-700' :
                                            member.status === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                                            'bg-gray-100 text-gray-700'
                                        }`}>
                                            {member.status === 'active' ? 'Aktif' :
                                             member.status === 'pending' ? 'Pending' : 'Inactive'}
                                        </span>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="text-center py-8">
                            <div className="text-4xl mb-2">👥</div>
                            <p className="text-gray-600">Belum ada anggota</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default CommunityDetailPage; 