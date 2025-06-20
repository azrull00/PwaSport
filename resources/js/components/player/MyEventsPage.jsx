import React, { useState, useEffect } from 'react';
import QRCode from 'qrcode';

const MyEventsPage = ({ userToken, onNavigate }) => {
    const [activeTab, setActiveTab] = useState('upcoming'); // 'upcoming' atau 'past'
    const [upcomingEvents, setUpcomingEvents] = useState([]);
    const [pastEvents, setPastEvents] = useState([]);
    const [isLoading, setIsLoading] = useState(false);
    const [error, setError] = useState('');
    const [showQRCode, setShowQRCode] = useState(null);
    const [userQR, setUserQR] = useState(null);

    useEffect(() => {
        if (userToken) {
            loadMyEvents();
            loadUserQR();
        }
    }, [userToken]);

    const loadMyEvents = async () => {
        setIsLoading(true);
        setError('');
        
        try {
            const response = await fetch('/api/users/my-events?per_page=50', {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            console.log('My events response:', data);
            
            if (data.status === 'success') {
                // Handle different response structures
                let events = [];
                if (data.data.events) {
                    events = Array.isArray(data.data.events) ? data.data.events : data.data.events.data || [];
                } else if (Array.isArray(data.data)) {
                    events = data.data;
                }
                
                const now = new Date();
                
                // Separate upcoming and past events
                const upcoming = events.filter(event => new Date(event.event_date) >= now);
                const past = events.filter(event => new Date(event.event_date) < now);
                
                setUpcomingEvents(upcoming);
                setPastEvents(past);
            } else {
                setError('Gagal memuat event Anda');
            }
        } catch (error) {
            console.error('Error loading my events:', error);
            setError('Terjadi kesalahan saat memuat event');
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

    const handleLeaveEvent = async (eventId) => {
        if (!confirm('Apakah Anda yakin ingin keluar dari event ini?')) return;

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
                // Refresh events
                loadMyEvents();
            } else {
                alert('Gagal keluar dari event: ' + data.message);
            }
        } catch (error) {
            console.error('Error leaving event:', error);
            alert('Terjadi kesalahan saat keluar dari event');
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

    const getEventStatus = (event) => {
        const now = new Date();
        const eventDate = new Date(event.event_date);
        const eventEndTime = new Date(`${event.event_date}T${event.end_time}`);
        
        if (eventEndTime < now) {
            return { text: 'Selesai', color: 'bg-gray-100 text-gray-700' };
        } else if (eventDate.toDateString() === now.toDateString()) {
            return { text: 'Hari Ini', color: 'bg-green-100 text-green-700' };
        } else if (eventDate > now) {
            const diffTime = Math.abs(eventDate - now);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            return { text: `${diffDays} hari lagi`, color: 'bg-blue-100 text-blue-700' };
        }
        return { text: 'Berlangsung', color: 'bg-orange-100 text-orange-700' };
    };

    const getParticipantStatus = (event) => {
        // This would come from the event participant relationship
        // For now, we'll assume all are confirmed
        return 'confirmed'; // confirmed, pending, rejected
    };

    const generateQRCode = async (qrString) => {
        try {
            // Generate proper QR code using qrcode library
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
            // Fallback to simple placeholder if QR generation fails
            return `data:image/svg+xml;base64,${btoa(`
                <svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">
                    <rect width="200" height="200" fill="white"/>
                    <rect x="10" y="10" width="20" height="20" fill="black"/>
                    <rect x="40" y="10" width="20" height="20" fill="black"/>
                    <rect x="70" y="10" width="20" height="20" fill="black"/>
                    <rect x="10" y="40" width="20" height="20" fill="black"/>
                    <rect x="70" y="40" width="20" height="20" fill="black"/>
                    <rect x="10" y="70" width="20" height="20" fill="black"/>
                    <rect x="40" y="70" width="20" height="20" fill="black"/>
                    <rect x="70" y="70" width="20" height="20" fill="black"/>
                    <text x="100" y="100" font-family="Arial" font-size="8" fill="black">${qrString}</text>
                </svg>
            `)}`;
        }
    };

    const EventCard = ({ event, isPast = false }) => {
        const status = getEventStatus(event);
        const participantStatus = getParticipantStatus(event);
        
        return (
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-3">
                {/* Header with title and status badge */}
                <div className="flex items-start justify-between mb-3">
                    <div className="flex-1 min-w-0 pr-3">
                        <h3 className="font-semibold text-gray-900 text-base leading-tight mb-2 break-words">
                            {event.title}
                        </h3>
                        <p className="text-sm text-gray-600 mb-3 line-clamp-2 break-words">
                            {event.description}
                        </p>
                    </div>
                    
                    <div className="flex flex-col items-end space-y-2 shrink-0">
                        <span className={`px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap ${status.color}`}>
                            {status.text}
                        </span>
                        <span className={`px-2 py-1 rounded-full text-xs font-medium whitespace-nowrap ${
                            participantStatus === 'confirmed' ? 'bg-green-100 text-green-700' :
                            participantStatus === 'pending' ? 'bg-yellow-100 text-yellow-700' :
                            'bg-red-100 text-red-700'
                        }`}>
                            {participantStatus === 'confirmed' ? 'Terkonfirmasi' :
                             participantStatus === 'pending' ? 'Menunggu' : 'Ditolak'}
                        </span>
                    </div>
                </div>

                {/* Event details in grid layout */}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                    <div className="space-y-2">
                        <div className="flex items-center text-sm text-gray-600">
                            <span className="mr-2 shrink-0">🏸</span>
                            <span className="truncate">{event.sport?.name || 'Sport'}</span>
                        </div>
                        
                        <div className="flex items-center text-sm text-gray-600">
                            <span className="mr-2 shrink-0">📅</span>
                            <span className="break-words text-xs sm:text-sm">{formatDate(event.event_date)}</span>
                        </div>
                        
                        <div className="flex items-center text-sm text-gray-600">
                            <span className="mr-2 shrink-0">⏰</span>
                            <span className="break-words text-xs sm:text-sm">
                                {formatTime(event.start_time)} - {formatTime(event.end_time)}
                            </span>
                        </div>
                    </div>
                    
                    <div className="space-y-2">
                        <div className="flex items-center text-sm text-gray-600">
                            <span className="mr-2 shrink-0">📍</span>
                            <span className="break-words text-xs sm:text-sm leading-tight">
                                {event.location_name}
                            </span>
                        </div>
                        
                        <div className="flex items-center text-sm text-gray-600">
                            <span className="mr-2 shrink-0">👥</span>
                            <span className="text-xs sm:text-sm">
                                {event.participants_count || 0}/{event.max_participants} peserta
                            </span>
                        </div>
                        
                        <div className="flex items-center">
                            <span className="mr-2 shrink-0 text-sm">💰</span>
                            <span className="text-sm font-semibold text-primary break-words">
                                {event.entry_fee ? `Rp ${event.entry_fee.toLocaleString()}` : 'Gratis'}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Action Buttons */}
                <div className="flex flex-wrap gap-2 mt-4 pt-3 border-t border-gray-100">
                    {!isPast && (
                        <>
                            <button
                                onClick={() => setShowQRCode(event.id)}
                                className="flex items-center px-3 py-2 bg-primary text-white text-sm rounded-lg hover:bg-primary-dark transition-colors"
                            >
                                <span className="mr-1">🎫</span>
                                QR Code
                            </button>
                            
                            <button
                                onClick={() => handleLeaveEvent(event.id)}
                                className="flex items-center px-3 py-2 bg-red-500 text-white text-sm rounded-lg hover:bg-red-600 transition-colors"
                            >
                                <span className="mr-1">🚪</span>
                                Keluar
                            </button>
                        </>
                    )}
                    
                    <button 
                        onClick={() => onNavigate('eventDetail', { eventId: event.id })}
                        className="flex items-center px-3 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200 transition-colors ml-auto"
                    >
                        <span className="mr-1">👁️</span>
                        Detail
                    </button>
                </div>
            </div>
        );
    };

    const QRCodeModal = ({ eventId, onClose }) => {
        const [qrCodeUrl, setQrCodeUrl] = useState(null);
        const [isGenerating, setIsGenerating] = useState(true);
        const event = [...upcomingEvents, ...pastEvents].find(e => e.id === eventId);
        
        useEffect(() => {
            const generateQR = async () => {
                if (event && userQR) {
                    setIsGenerating(true);
                    try {
                        const qrUrl = await generateQRCode(userQR.qr_data);
                        setQrCodeUrl(qrUrl);
                    } catch (error) {
                        console.error('Failed to generate QR code:', error);
                    } finally {
                        setIsGenerating(false);
                    }
                }
            };
            
            generateQR();
        }, [event, userQR]);
        
        if (!event || !userQR) return null;

        return (
            <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
                <div className="bg-white rounded-xl p-6 max-w-sm w-full">
                    <div className="text-center mb-4">
                        <h3 className="text-lg font-semibold text-gray-900 mb-2">QR Code Check-in</h3>
                        <p className="text-sm text-gray-600">{event.title}</p>
                    </div>
                    
                    <div className="flex justify-center mb-4">
                        {isGenerating ? (
                            <div className="w-48 h-48 border border-gray-200 rounded-lg flex items-center justify-center">
                                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                            </div>
                        ) : (
                            <img 
                                src={qrCodeUrl}
                                alt="QR Code"
                                className="w-48 h-48 border border-gray-200 rounded-lg"
                            />
                        )}
                    </div>
                    
                    <div className="text-center mb-4">
                        <p className="text-sm text-gray-600">
                            Tunjukkan QR code ini kepada host untuk check-in
                        </p>
                    </div>
                    
                    <button
                        onClick={onClose}
                        className="w-full bg-gray-100 text-gray-700 py-2 rounded-lg font-medium hover:bg-gray-200 transition-colors"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        );
    };

    if (!userToken) {
        return (
            <div className="flex items-center justify-center h-64">
                <div className="text-center">
                    <p className="text-gray-600">Token tidak ditemukan. Silakan login kembali.</p>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-secondary min-h-screen">
            {/* Header */}
            <div className="bg-white border-b border-gray-100 px-4 py-3">
                <h1 className="text-lg font-semibold text-gray-900">Event Saya</h1>
            </div>

            {/* Tabs */}
            <div className="px-4 py-4">
                <div className="flex bg-gray-100 rounded-xl p-1">
                    <button
                        onClick={() => setActiveTab('upcoming')}
                        className={`flex-1 py-2 px-4 rounded-lg font-medium transition-colors ${
                            activeTab === 'upcoming'
                                ? 'bg-white text-primary shadow-sm'
                                : 'text-gray-600 hover:text-gray-800'
                        }`}
                    >
                        Mendatang ({upcomingEvents.length})
                    </button>
                    <button
                        onClick={() => setActiveTab('past')}
                        className={`flex-1 py-2 px-4 rounded-lg font-medium transition-colors ${
                            activeTab === 'past'
                                ? 'bg-white text-primary shadow-sm'
                                : 'text-gray-600 hover:text-gray-800'
                        }`}
                    >
                        Selesai ({pastEvents.length})
                    </button>
                </div>
            </div>

            {/* Content */}
            <div className="px-4 pb-20">
                {error && (
                    <div className="bg-red-50 border border-red-200 rounded-xl p-4 mb-4">
                        <p className="text-red-600 text-sm">{error}</p>
                    </div>
                )}

                {isLoading ? (
                    <div className="flex justify-center py-8">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
                    </div>
                ) : (
                    <div>
                        {activeTab === 'upcoming' ? (
                            upcomingEvents.length > 0 ? (
                                upcomingEvents.map((event) => (
                                    <EventCard key={event.id} event={event} />
                                ))
                            ) : (
                                <div className="text-center py-8">
                                    <div className="text-6xl mb-4">📅</div>
                                    <h3 className="font-semibold text-gray-900 mb-2">Belum ada event mendatang</h3>
                                    <p className="text-gray-600 text-sm mb-4">Jelajahi dan ikuti event yang menarik</p>
                                    <button 
                                        onClick={() => onNavigate('discover')}
                                        className="bg-primary text-white px-6 py-3 rounded-xl font-medium"
                                    >
                                        Cari Event
                                    </button>
                                </div>
                            )
                        ) : (
                            pastEvents.length > 0 ? (
                                pastEvents.map((event) => (
                                    <EventCard key={event.id} event={event} isPast={true} />
                                ))
                            ) : (
                                <div className="text-center py-8">
                                    <div className="text-6xl mb-4">📋</div>
                                    <h3 className="font-semibold text-gray-900 mb-2">Belum ada riwayat event</h3>
                                    <p className="text-gray-600 text-sm">Event yang telah selesai akan muncul di sini</p>
                                </div>
                            )
                        )}
                    </div>
                )}
            </div>

            {/* QR Code Modal */}
            {showQRCode && (
                <QRCodeModal 
                    eventId={showQRCode} 
                    onClose={() => setShowQRCode(null)} 
                />
            )}
        </div>
    );
};

export default MyEventsPage; 