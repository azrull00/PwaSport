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
    const [ratings, setRatings] = useState([]);
    const [userRating, setUserRating] = useState(null);
    const [pastEvents, setPastEvents] = useState([]);
    const [selectedEvent, setSelectedEvent] = useState(null);
    const [showRatingForm, setShowRatingForm] = useState(false);
    const [ratingData, setRatingData] = useState({
        event_id: null,
        skill_rating: 5,
        hospitality_rating: 5,
        review: ''
    });
    const [isLoading, setIsLoading] = useState(true);
    const [isJoining, setIsJoining] = useState(false);
    const [isLeaving, setIsLeaving] = useState(false);
    const [isSubmittingRating, setIsSubmittingRating] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (communityId && userToken) {
            loadCommunityDetails();
            loadMembers();
            loadRatings();
            loadPastEvents();
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

    const loadRatings = async () => {
        try {
            const response = await fetch(`/api/communities/${communityId}/ratings`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (data.status === 'success') {
                const ratings = data.data.ratings?.data || [];
                setRatings(ratings);
                
                // Check if current user has already rated for any event
                const userId = getUserIdFromToken(userToken);
                if (userId) {
                    const existingRating = ratings.find(r => r.user_id === parseInt(userId));
                    setUserRating(existingRating || null);
                    
                    if (existingRating) {
                        setRatingData({
                            event_id: existingRating.event_id,
                            skill_rating: existingRating.skill_rating,
                            hospitality_rating: existingRating.hospitality_rating,
                            review: existingRating.review || ''
                        });
                    }
                }
            }
        } catch (error) {
            console.error('Error loading ratings:', error);
        }
    };

    const loadPastEvents = async () => {
        try {
            const response = await fetch(`/api/communities/${communityId}/user-past-events`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (data.status === 'success') {
                setPastEvents(data.data.events || []);
            }
        } catch (error) {
            console.error('Error loading past events:', error);
        }
    };

    const submitRating = async () => {
        if (!userMembership || userMembership.status !== 'active') {
            setError('Hanya anggota aktif yang dapat memberikan rating');
            return;
        }

        if (!ratingData.event_id) {
            setError('Pilih event yang ingin Anda rating terlebih dahulu');
            return;
        }

        setIsSubmittingRating(true);
        setError('');

        try {
            const response = await fetch(`/api/communities/${communityId}/rate`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(ratingData)
            });

            const data = await response.json();
            if (data.status === 'success') {
                // Reload ratings, community details, and past events
                await loadRatings();
                await loadCommunityDetails();
                await loadPastEvents();
                setShowRatingForm(false);
                setSelectedEvent(null);
                setError('');
            } else {
                setError(data.message || 'Gagal memberikan rating');
            }
        } catch (error) {
            console.error('Error submitting rating:', error);
            setError('Terjadi kesalahan saat memberikan rating');
        } finally {
            setIsSubmittingRating(false);
        }
    };

    const handleRatingChange = (type, value) => {
        setRatingData(prev => ({
            ...prev,
            [type]: value
        }));
    };

    const handleEventSelection = (event) => {
        setSelectedEvent(event);
        setRatingData(prev => ({
            ...prev,
            event_id: event.id
        }));
        setShowRatingForm(true);
    };

    const cancelRating = () => {
        setShowRatingForm(false);
        setSelectedEvent(null);
        setRatingData({
            event_id: null,
            skill_rating: 5,
            hospitality_rating: 5,
            review: ''
        });
        setError('');
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
                        <div>
                            <span className="text-xs font-medium text-gray-500">Rating Skill</span>
                            <p className="font-medium text-gray-900">
                                ⭐ {community.average_skill_rating ? parseFloat(community.average_skill_rating).toFixed(1) : 'N/A'}
                            </p>
                        </div>
                        <div>
                            <span className="text-xs font-medium text-gray-500">Rating Hospitality</span>
                            <p className="font-medium text-gray-900">
                                ❤️ {community.hospitality_rating ? parseFloat(community.hospitality_rating).toFixed(1) : 'N/A'}
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

                {/* Community Ratings Section */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-semibold text-gray-900">
                            Rating Komunitas ({ratings.length})
                        </h2>
                        
                        {userMembership && userMembership.status === 'active' && (
                            <button
                                onClick={() => setShowRatingForm(!showRatingForm)}
                                className="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors"
                            >
                                {userRating ? '✏️ Edit Rating' : '⭐ Beri Rating'}
                            </button>
                        )}
                    </div>

                    {/* Community Ratings Display */}
                    {ratings && ratings.length > 0 && (
                        <div className="bg-white rounded-lg shadow-md p-6">
                            <h3 className="text-lg font-bold text-gray-800 mb-4">
                                💬 Rating & Review Komunitas
                            </h3>
                            <div className="space-y-4">
                                {ratings.slice(0, 5).map((rating, index) => (
                                    <div key={index} className="border-b border-gray-200 pb-4 last:border-b-0">
                                        <div className="flex items-start justify-between">
                                            <div className="flex items-center space-x-3">
                                                <div className="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-bold">
                                                    {rating.user?.name?.charAt(0).toUpperCase() || '?'}
                                                </div>
                                                <div>
                                                    <h4 className="font-medium text-gray-900">
                                                        {rating.user?.name || 'User'}
                                                    </h4>
                                                    <p className="text-xs text-gray-500">
                                                        Event: {rating.event?.title || 'Event tidak ditemukan'}
                                                    </p>
                                                    <p className="text-xs text-gray-400">
                                                        {new Date(rating.created_at).toLocaleDateString('id-ID', {
                                                            day: 'numeric',
                                                            month: 'short',
                                                            year: 'numeric'
                                                        })}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <div className="flex items-center space-x-2 text-sm">
                                                    <span className="text-yellow-500">⭐ {rating.skill_rating}</span>
                                                    <span className="text-red-500">❤️ {rating.hospitality_rating}</span>
                                                </div>
                                                <p className="text-xs text-gray-500">
                                                    Avg: {((rating.skill_rating + rating.hospitality_rating) / 2).toFixed(1)}
                                                </p>
                                            </div>
                                        </div>
                                        {rating.review && (
                                            <div className="mt-3 ml-13">
                                                <p className="text-gray-700 text-sm bg-gray-50 p-3 rounded-lg">
                                                    "{rating.review}"
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                ))}
                                {ratings.length > 5 && (
                                    <div className="text-center pt-4">
                                        <p className="text-sm text-gray-500">
                                            Dan {ratings.length - 5} rating lainnya...
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Rating System */}
                    {userMembership && userMembership.status === 'active' && (
                        <div className="bg-white rounded-lg shadow-md p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg font-bold text-gray-800">
                                    📊 Rating Komunitas
                                </h3>
                                {pastEvents.length > 0 && !showRatingForm && (
                                    <button
                                        onClick={() => setShowRatingForm(true)}
                                        className="px-4 py-2 bg-blue-500 text-white rounded-lg font-medium hover:bg-blue-600 transition-colors"
                                    >
                                        Beri Rating
                                    </button>
                                )}
                            </div>

                            {pastEvents.length === 0 && (
                                <div className="text-center py-4 text-gray-500">
                                    <p>Anda belum pernah mengikuti event di komunitas ini.</p>
                                    <p className="text-sm">Ikuti event terlebih dahulu untuk dapat memberikan rating.</p>
                                </div>
                            )}

                            {/* Event Selection */}
                            {showRatingForm && !selectedEvent && pastEvents.length > 0 && (
                                <div className="space-y-4">
                                    <div className="border-b pb-4">
                                        <h4 className="text-md font-semibold text-gray-700 mb-3">
                                            Pilih Event yang Ingin Anda Rating:
                                        </h4>
                                        <div className="space-y-2">
                                            {pastEvents.map(event => (
                                                <div
                                                    key={event.id}
                                                    onClick={() => handleEventSelection(event)}
                                                    className="p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors"
                                                >
                                                    <div className="flex justify-between items-start">
                                                        <div>
                                                            <h5 className="font-medium text-gray-800">{event.title}</h5>
                                                            <p className="text-sm text-gray-600">{event.sport?.name}</p>
                                                            <p className="text-xs text-gray-500">
                                                                {new Date(event.event_date).toLocaleDateString('id-ID', {
                                                                    weekday: 'long',
                                                                    year: 'numeric',
                                                                    month: 'long',
                                                                    day: 'numeric'
                                                                })}
                                                            </p>
                                                        </div>
                                                        <span className="text-blue-500 text-sm">
                                                            Pilih →
                                                        </span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="flex justify-end">
                                        <button
                                            onClick={cancelRating}
                                            className="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-colors"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* Rating Form */}
                            {showRatingForm && selectedEvent && (
                                <div className="space-y-4">
                                    <div className="border-b pb-4">
                                        <h4 className="text-md font-semibold text-gray-700 mb-2">
                                            Rating untuk Event: {selectedEvent.title}
                                        </h4>
                                        <p className="text-sm text-gray-600">
                                            {selectedEvent.sport?.name} • {new Date(selectedEvent.event_date).toLocaleDateString('id-ID')}
                                        </p>
                                    </div>

                                    {error && (
                                        <div className="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                                            {error}
                                        </div>
                                    )}

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        {/* Skill Rating */}
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                ⭐ Skill Level Komunitas (1-5):
                                            </label>
                                            <div className="flex space-x-2">
                                                {[1, 2, 3, 4, 5].map(star => (
                                                    <button
                                                        key={star}
                                                        type="button"
                                                        onClick={() => handleRatingChange('skill_rating', star)}
                                                        className={`text-2xl transition-colors ${
                                                            star <= ratingData.skill_rating 
                                                                ? 'text-yellow-400' 
                                                                : 'text-gray-300'
                                                        }`}
                                                    >
                                                        ⭐
                                                    </button>
                                                ))}
                                            </div>
                                            <p className="text-xs text-gray-500 mt-1">
                                                Rating: {ratingData.skill_rating}/5
                                            </p>
                                        </div>

                                        {/* Hospitality Rating */}
                                        <div>
                                            <label className="block text-sm font-medium text-gray-700 mb-2">
                                                ❤️ Hospitalitas Komunitas (1-5):
                                            </label>
                                            <div className="flex space-x-2">
                                                {[1, 2, 3, 4, 5].map(heart => (
                                                    <button
                                                        key={heart}
                                                        type="button"
                                                        onClick={() => handleRatingChange('hospitality_rating', heart)}
                                                        className={`text-2xl transition-colors ${
                                                            heart <= ratingData.hospitality_rating 
                                                                ? 'text-red-500' 
                                                                : 'text-gray-300'
                                                        }`}
                                                    >
                                                        ❤️
                                                    </button>
                                                ))}
                                            </div>
                                            <p className="text-xs text-gray-500 mt-1">
                                                Rating: {ratingData.hospitality_rating}/5
                                            </p>
                                        </div>
                                    </div>

                                    {/* Review */}
                                    <div>
                                        <label className="block text-sm font-medium text-gray-700 mb-2">
                                            💬 Review (Opsional):
                                        </label>
                                        <textarea
                                            value={ratingData.review}
                                            onChange={(e) => handleRatingChange('review', e.target.value)}
                                            className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                                            rows="3"
                                            maxLength="500"
                                            placeholder="Ceritakan pengalaman Anda di komunitas ini..."
                                        />
                                        <p className="text-xs text-gray-500 mt-1">
                                            {ratingData.review.length}/500 karakter
                                        </p>
                                    </div>

                                    {/* Submit Buttons */}
                                    <div className="flex space-x-3 pt-4">
                                        <button
                                            onClick={submitRating}
                                            disabled={isSubmittingRating}
                                            className="flex-1 px-4 py-3 bg-blue-500 text-white rounded-lg font-semibold hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                        >
                                            {isSubmittingRating ? 'Menyimpan...' : (userRating ? 'Update Rating' : 'Kirim Rating')}
                                        </button>
                                        <button
                                            onClick={cancelRating}
                                            className="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-colors"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
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