import React, { useState, useEffect } from 'react';
import { HiArrowLeft, HiLocationMarker, HiCalendar, HiClock, HiUsers, HiStar, HiOfficeBuilding } from 'react-icons/hi';
import { HiUserGroup, HiEye } from 'react-icons/hi2';
import QRCode from 'qrcode';
import { getUserIdFromToken } from '../../utils/auth';
import DistanceDisplay from '../common/DistanceDisplay';
import LocationPicker from '../common/LocationPicker';

const EventDetailPage = ({ eventId, userToken, onNavigate, onBack }) => {
    const [event, setEvent] = useState(null);
    const [participants, setParticipants] = useState([]);
    const [userParticipation, setUserParticipation] = useState(null);
    const [isLoading, setIsLoading] = useState(true);
    const [isJoining, setIsJoining] = useState(false);
    const [isLeaving, setIsLeaving] = useState(false);
    const [error, setError] = useState('');
    const [showQRCode, setShowQRCode] = useState(false);
    const [userQR, setUserQR] = useState(null);
    const [qrCodeUrl, setQrCodeUrl] = useState(null);
    const [isHost, setIsHost] = useState(false);
    const [showMap, setShowMap] = useState(false);

    useEffect(() => {
        if (eventId && userToken) {
            loadEventDetails();
            loadParticipants();
            loadUserQR();
        }
    }, [eventId, userToken]);

    const loadEventDetails = async () => {
        try {
            const response = await fetch(`/api/events/${eventId}`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (data.status === 'success') {
                setEvent(data.data.event);
                
                // Check if user is already a participant and if user is host
                const userId = getUserIdFromToken(userToken);
                if (userId) {
                    const userParticipant = data.data.event.participants?.find(p => p.user_id === userId);
                    setUserParticipation(userParticipant || null);
                    
                    // Check if user is the host
                    setIsHost(data.data.event.host_id === parseInt(userId));
                } else {
                    setUserParticipation(null);
                    setIsHost(false);
                }
            } else {
                setError('Gagal memuat detail event');
            }
        } catch (error) {
            console.error('Error loading event details:', error);
            setError('Terjadi kesalahan saat memuat event');
        }
    };

    const loadParticipants = async () => {
        try {
            const response = await fetch(`/api/events/${eventId}/participants`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (data.status === 'success') {
                setParticipants(data.data.participants || []);
            }
        } catch (error) {
            console.error('Error loading participants:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const loadUserQR = async () => {
        try {
            const response = await fetch('/api/users/my-qr-code', {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (data.status === 'success') {
                setUserQR(data.data);
            }
        } catch (error) {
            console.error('Error loading QR code:', error);
        }
    };

    const handleJoinEvent = async () => {
        if (!event || isJoining) return;

        setIsJoining(true);
        setError('');

        try {
            const response = await fetch(`/api/events/${eventId}/join`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (data.status === 'success') {
                // Show success message and redirect to My Events
                alert('Berhasil bergabung dengan event!');
                onNavigate('events'); // Redirect to My Events page
            } else {
                setError(data.message || 'Gagal bergabung dengan event');
            }
        } catch (error) {
            console.error('Error joining event:', error);
            setError('Terjadi kesalahan saat bergabung dengan event');
        } finally {
            setIsJoining(false);
        }
    };

    const handleLeaveEvent = async () => {
        if (!event || !userParticipation || isLeaving) return;

        if (!confirm('Apakah Anda yakin ingin keluar dari event ini?')) return;

        setIsLeaving(true);
        setError('');

        try {
            const response = await fetch(`/api/events/${eventId}/leave`, {
                method: 'DELETE',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            if (data.status === 'success') {
                // Reload event details and participants
                await loadEventDetails();
                await loadParticipants();
            } else {
                setError(data.message || 'Gagal keluar dari event');
            }
        } catch (error) {
            console.error('Error leaving event:', error);
            setError('Terjadi kesalahan saat keluar dari event');
        } finally {
            setIsLeaving(false);
        }
    };

    const generateQRCode = async (qrString) => {
        try {
            const qrCodeDataURL = await QRCode.toDataURL(qrString, {
                width: 200,
                margin: 2,
                color: {
                    dark: '#000000',
                    light: '#FFFFFF'
                }
            });
            return qrCodeDataURL;
        } catch (error) {
            console.error('Error generating QR code:', error);
            return null;
        }
    };

    const handleShowQRCode = async () => {
        if (!userQR) return;
        
        try {
            const qrUrl = await generateQRCode(userQR.qr_data);
            setQrCodeUrl(qrUrl);
            setShowQRCode(true);
        } catch (error) {
            console.error('Error generating QR code:', error);
        }
    };

    const formatDate = (dateString) => {
        const date = new Date(dateString);
        return date.toLocaleDateString('id-ID', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    };

    const formatTime = (timeString) => {
        return new Date(`2000-01-01T${timeString}`).toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const getEventStatus = () => {
        if (!event) return { text: '', color: '' };
        
        const now = new Date();
        const eventDate = new Date(event.event_date);
        
        if (eventDate < now) {
            return { text: 'Selesai', color: 'bg-gray-100 text-gray-700' };
        } else if (eventDate.toDateString() === now.toDateString()) {
            return { text: 'Hari Ini', color: 'bg-green-100 text-green-700' };
        } else {
            const diffTime = Math.abs(eventDate - now);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            return { text: `${diffDays} hari lagi`, color: 'bg-blue-100 text-blue-700' };
        }
    };

    if (isLoading) {
        return (
            <div className="bg-secondary min-h-screen">
                <div className="bg-white border-b border-gray-100 px-4 py-3 flex items-center">
                    <button
                        onClick={onBack}
                        className="p-1 hover:bg-gray-100 rounded-lg transition-colors mr-3"
                    >
                        <HiArrowLeft className="w-6 h-6 text-gray-600" />
                    </button>
                    <h1 className="text-lg font-semibold text-gray-900">Detail Event</h1>
                </div>
                
                <div className="flex justify-center py-8">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                </div>
            </div>
        );
    }

    if (!event) {
        return (
            <div className="bg-secondary min-h-screen">
                <div className="bg-white border-b border-gray-100 px-4 py-3 flex items-center">
                    <button
                        onClick={onBack}
                        className="p-1 hover:bg-gray-100 rounded-lg transition-colors mr-3"
                    >
                        <HiArrowLeft className="w-6 h-6 text-gray-600" />
                    </button>
                    <h1 className="text-lg font-semibold text-gray-900">Detail Event</h1>
                </div>
                
                <div className="text-center py-8">
                    <p className="text-gray-600">Event tidak ditemukan</p>
                </div>
            </div>
        );
    }

    const status = getEventStatus();
    const isPastEvent = new Date(event.event_date) < new Date();
    const isUserParticipant = !!userParticipation;
    const canShowQR = isUserParticipant && !isPastEvent && userParticipation.status === 'confirmed';

    return (
        <div className="bg-secondary min-h-screen">
            {/* Header */}
            <div className="bg-white border-b border-gray-100 px-4 py-3 flex items-center">
                <button
                    onClick={onBack}
                    className="p-1 hover:bg-gray-100 rounded-lg transition-colors mr-3"
                >
                    <HiArrowLeft className="w-6 h-6 text-gray-600" />
                </button>
                <h1 className="text-lg font-semibold text-gray-900">Detail Event</h1>
            </div>

            <div className="px-4 py-4">
                {/* Error Message */}
                {error && (
                    <div className="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                        <p className="text-red-600 text-sm">{error}</p>
                    </div>
                )}

                {/* Event Info */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
                    <div className="flex items-start justify-between mb-4">
                        <div className="flex-1">
                            <h2 className="text-xl font-bold text-gray-900 mb-2">{event.title}</h2>
                            <span className={`px-3 py-1 rounded-full text-sm font-medium ${status.color}`}>
                                {status.text}
                            </span>
                        </div>
                        <div className="text-right">
                            <div className="text-lg font-bold text-primary mb-1">
                                {event.entry_fee ? `Rp ${event.entry_fee.toLocaleString()}` : 'Gratis'}
                            </div>
                        </div>
                    </div>

                    <p className="text-gray-600 mb-4">{event.description}</p>

                    <div className="space-y-3">
                        <div className="flex items-center text-gray-700">
                            <span className="mr-3 text-blue-600">🏸</span>
                            <span>{event.sport?.name || 'Sport'}</span>
                        </div>
                        
                        <div className="flex items-center text-gray-700">
                            <HiCalendar className="w-5 h-5 mr-3 text-blue-600" />
                            <span>{formatDate(event.event_date)}</span>
                        </div>
                        
                        <div className="flex items-center text-gray-700">
                            <HiClock className="w-5 h-5 mr-3 text-blue-600" />
                            <span>{formatTime(event.start_time)} - {formatTime(event.end_time)}</span>
                        </div>
                        
                        <div className="flex items-center text-gray-700">
                            <HiLocationMarker className="w-5 h-5 mr-3 text-blue-600" />
                            <span>{event.location_name}</span>
                        </div>
                        
                        <div className="flex items-center text-gray-700">
                            <HiUsers className="w-5 h-5 mr-3 text-blue-600" />
                            <span>{participants.length}/{event.max_participants} peserta</span>
                        </div>

                        {event.skill_level_required && (
                            <div className="flex items-center text-gray-700">
                                <HiStar className="w-5 h-5 mr-3 text-blue-600" />
                                <span>Level: {event.skill_level_required}</span>
                            </div>
                        )}

                        {event.community && (
                            <div className="flex items-center text-gray-700">
                                <HiOfficeBuilding className="w-5 h-5 mr-3 text-blue-600" />
                                <span>Komunitas: {event.community.name}</span>
                            </div>
                        )}

                        {event.latitude && event.longitude && (
                            <div className="mb-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <DistanceDisplay 
                                            targetLat={event.latitude} 
                                            targetLng={event.longitude}
                                            showRealTime={true}
                                        />
                                        <span className="text-sm text-gray-600">{event.address}</span>
                                    </div>
                                    <button
                                        onClick={() => setShowMap(!showMap)}
                                        className="text-primary hover:text-primary-dark"
                                    >
                                        {showMap ? 'Hide Map' : 'Show Map'}
                                    </button>
                                </div>

                                {showMap && (
                                    <div className="mt-4">
                                        <LocationPicker
                                            initialLocation={{ lat: event.latitude, lng: event.longitude }}
                                            showCurrentLocation={true}
                                            height="300px"
                                            onLocationSelect={() => {}} // Read-only
                                        />
                                    </div>
                                )}
                            </div>
                        )}
                    </div>

                    {/* Action Buttons */}
                    <div className="mt-6 space-y-3">
                        {!isPastEvent && (
                            <div className="flex space-x-3">
                                {!isUserParticipant ? (
                                    <button
                                        onClick={handleJoinEvent}
                                        disabled={isJoining}
                                        className="flex-1 bg-primary text-white py-3 rounded-lg font-medium hover:bg-primary-dark transition-colors disabled:opacity-50"
                                    >
                                        {isJoining ? 'Bergabung...' : 'Bergabung'}
                                    </button>
                                ) : (
                                    <>
                                        {canShowQR && (
                                            <button
                                                onClick={handleShowQRCode}
                                                className="flex-1 bg-primary text-white py-3 rounded-lg font-medium hover:bg-primary-dark transition-colors"
                                            >
                                                🎫 QR Code
                                            </button>
                                        )}
                                        {!isHost && (
                                        <button
                                            onClick={handleLeaveEvent}
                                            disabled={isLeaving}
                                            className="flex-1 bg-red-500 text-white py-3 rounded-lg font-medium hover:bg-red-600 transition-colors disabled:opacity-50"
                                        >
                                            {isLeaving ? 'Keluar...' : 'Keluar'}
                                        </button>
                                        )}
                                    </>
                                )}
                            </div>
                        )}

                        {/* Host Management Buttons */}
                        {isHost && !isPastEvent && (
                            <div className="grid grid-cols-1 gap-3">
                                <button
                                    onClick={() => onNavigate('courtManagement', { eventId: event.id })}
                                    className="w-full bg-orange-500 text-white py-3 px-4 rounded-lg font-medium hover:bg-orange-600 transition-colors flex items-center justify-center"
                                >
                                    <span className="mr-2">🏸</span>
                                    Kelola Court & Queue
                                </button>
                                <button
                                    onClick={() => onNavigate('matchmakingStatus', { eventId: event.id })}
                                    className="w-full bg-blue-500 text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-600 transition-colors flex items-center justify-center"
                                >
                                    <span className="mr-2">⚡</span>
                                    Lihat Status Matchmaking
                                </button>
                            </div>
                        )}

                        {/* Player View Buttons - Show for participants */}
                        {isUserParticipant && !isHost && !isPastEvent && (
                            <div className="grid grid-cols-1 gap-3">
                                <button
                                    onClick={() => onNavigate('matchmakingStatus', { eventId: event.id })}
                                    className="w-full bg-blue-500 text-white py-3 px-4 rounded-lg font-medium hover:bg-blue-600 transition-colors flex items-center justify-center"
                                >
                                    <HiEye className="w-5 h-5 mr-2" />
                                    Lihat Status Matchmaking
                                </button>
                            </div>
                        )}
                    </div>

                    {/* Participation Status - Enhanced */}
                    {isUserParticipant && (
                        <div className="mt-4 p-4 rounded-lg bg-green-50 border-2 border-green-200">
                            <div className="flex items-center">
                                <div className="w-3 h-3 bg-green-500 rounded-full mr-3 animate-pulse"></div>
                                <div className="flex-1">
                                    <p className="text-green-800 font-semibold">
                                        ✅ Anda sudah bergabung dengan event ini
                                    </p>
                                    <p className="text-green-700 text-sm mt-1">
                                        Status: {userParticipation?.status === 'confirmed' ? '✅ Terkonfirmasi' : 
                                                userParticipation?.status === 'waiting' ? '⏳ Dalam Antrian' : 
                                                userParticipation?.status === 'checked_in' ? '🎫 Sudah Check-in' : 
                                                userParticipation?.status === 'registered' ? '📝 Terdaftar' :
                                                '👥 Bergabung'}
                            </p>
                                    {userParticipation?.queue_position && (
                                        <p className="text-green-600 text-sm mt-1">
                                            Posisi antrian: #{userParticipation.queue_position}
                                        </p>
                                    )}
                                    {userParticipation?.registered_at && (
                                        <p className="text-green-600 text-xs mt-1">
                                            Terdaftar pada: {new Date(userParticipation.registered_at).toLocaleString('id-ID')}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* Participants List */}
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Peserta ({participants.length})</h3>
                    
                    {participants.length > 0 ? (
                        <div className="space-y-3">
                            {participants.slice(0, 10).map((participant) => (
                                <div key={participant.id} className="flex items-center justify-between">
                                    <div className="flex items-center space-x-3">
                                        <div className="w-10 h-10 bg-primary rounded-full flex items-center justify-center">
                                            <span className="text-white font-medium text-sm">
                                                {participant.user?.name?.charAt(0) || 'U'}
                                            </span>
                                        </div>
                                        <div>
                                            <p className="font-medium text-gray-900">
                                                {participant.user?.name || 'User'}
                                            </p>
                                            <p className="text-sm text-gray-500">
                                                {participant.status === 'confirmed' ? 'Terkonfirmasi' :
                                                 participant.status === 'waiting' ? 'Waiting List' :
                                                 participant.status === 'checked_in' ? 'Sudah Check-in' :
                                                 'Terdaftar'}
                                            </p>
                                        </div>
                                    </div>
                                    
                                    {participant.status === 'checked_in' && (
                                        <span className="text-green-600 text-sm">✓</span>
                                    )}
                                </div>
                            ))}
                            
                            {participants.length > 10 && (
                                <p className="text-gray-500 text-sm text-center pt-2">
                                    Dan {participants.length - 10} peserta lainnya
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="text-center py-8">
                            <div className="text-4xl mb-2">👥</div>
                            <p className="text-gray-500">Belum ada peserta yang terdaftar</p>
                        </div>
                    )}
                </div>
            </div>

            {/* QR Code Modal */}
            {showQRCode && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-xl p-6 max-w-sm w-full">
                        <div className="text-center mb-4">
                            <h3 className="text-lg font-semibold text-gray-900 mb-2">QR Code Check-in</h3>
                            <p className="text-sm text-gray-600">{event.title}</p>
                        </div>
                        
                        <div className="flex justify-center mb-4">
                            {qrCodeUrl ? (
                                <img 
                                    src={qrCodeUrl}
                                    alt="QR Code"
                                    className="w-48 h-48 border border-gray-200 rounded-lg"
                                />
                            ) : (
                                <div className="w-48 h-48 border border-gray-200 rounded-lg flex items-center justify-center">
                                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                                </div>
                            )}
                        </div>
                        
                        <div className="text-center mb-4">
                            <p className="text-sm text-gray-600">
                                Tunjukkan QR code ini kepada host untuk check-in
                            </p>
                        </div>
                        
                        <button
                            onClick={() => setShowQRCode(false)}
                            className="w-full bg-gray-100 text-gray-700 py-2 rounded-lg font-medium hover:bg-gray-200 transition-colors"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
};

export default EventDetailPage; 