import React, { useState, useEffect } from 'react';
import { HiArrowLeft } from 'react-icons/hi';

const MatchmakingStatusPage = ({ userToken, onNavigate, onBack, eventId = null }) => {
    const [matchmakingData, setMatchmakingData] = useState({
        ongoing_matches: [],
        scheduled_matches: [],
        upcoming_events: [],
        summary: {}
    });
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');

    useEffect(() => {
        fetchMatchmakingStatus();
    }, [userToken, eventId]);

    const fetchMatchmakingStatus = async () => {
        try {
            setLoading(true);
            
            // If eventId is provided, fetch specific event matchmaking status
            let url = '/api/users/my-matchmaking-status';
            if (eventId) {
                url = `/api/matchmaking/${eventId}/event-matchmaking-status`;
            }
            
            const response = await fetch(url, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            });

            if (response.ok) {
                const data = await response.json();
                if (data.status === 'success') {
                    setMatchmakingData(data.data);
                }
            } else {
                setError('Gagal mengambil status matchmaking');
            }
        } catch (error) {
            console.error('Error fetching matchmaking status:', error);
            setError('Terjadi kesalahan saat mengambil data');
        } finally {
            setLoading(false);
        }
    };

    const formatDateTime = (dateTime) => {
        const date = new Date(dateTime);
        return new Intl.DateTimeFormat('id-ID', {
            weekday: 'short',
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    };

    if (loading) {
        return (
            <div className="min-h-screen bg-gray-50 flex items-center justify-center">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
                    <p className="text-gray-600">Memuat status matchmaking...</p>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="min-h-screen bg-gray-50 flex items-center justify-center">
                <div className="text-center">
                    <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg className="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <h3 className="font-semibold text-gray-900 mb-2">Error</h3>
                    <p className="text-gray-600 mb-4">{error}</p>
                    <button 
                        onClick={fetchMatchmakingStatus}
                        className="bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-dark transition-colors"
                    >
                        Coba Lagi
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50">
            {/* Header */}
            <div className="bg-white shadow-sm border-b border-gray-200">
                <div className="flex items-center p-4">
                    <button 
                        onClick={onBack}
                        className="mr-3 p-2 hover:bg-gray-100 rounded-full transition-colors"
                    >
                        <HiArrowLeft className="w-6 h-6 text-gray-600" />
                    </button>
                    <h1 className="text-xl font-semibold text-gray-900">
                        {eventId ? `Status Matchmaking - ${matchmakingData.event?.title || 'Event'}` : 'Status Matchmaking'}
                    </h1>
                </div>
            </div>

            {/* Summary Cards */}
            <div className="p-4">
                <div className="grid grid-cols-3 gap-3 mb-6">
                    <div className="bg-white rounded-lg p-4 text-center shadow-sm">
                        <div className="text-2xl font-bold text-primary">{matchmakingData.summary.ongoing_count || 0}</div>
                        <div className="text-sm text-gray-600">Sedang Berlangsung</div>
                    </div>
                    <div className="bg-white rounded-lg p-4 text-center shadow-sm">
                        <div className="text-2xl font-bold text-blue-600">{matchmakingData.summary.scheduled_count || 0}</div>
                        <div className="text-sm text-gray-600">Terjadwal</div>
                    </div>
                    <div className="bg-white rounded-lg p-4 text-center shadow-sm">
                        <div className="text-2xl font-bold text-green-600">
                            {eventId ? 
                                (matchmakingData.summary.waiting_count || 0) : 
                                (matchmakingData.summary.upcoming_events_count || 0)
                            }
                        </div>
                        <div className="text-sm text-gray-600">
                            {eventId ? 'Dalam Antrian' : 'Event Mendatang'}
                        </div>
                    </div>
                </div>

                {/* Event-specific info */}
                {eventId && matchmakingData.event && (
                    <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <h3 className="font-semibold text-blue-900 mb-2">Info Event</h3>
                        <div className="space-y-1 text-sm text-blue-800">
                            <p><span className="font-medium">Sport:</span> {matchmakingData.event.sport?.name}</p>
                            <p><span className="font-medium">Lokasi:</span> {matchmakingData.event.location}</p>
                            <p><span className="font-medium">Tanggal:</span> {new Date(matchmakingData.event.date).toLocaleDateString('id-ID')}</p>
                            {matchmakingData.summary.total_participants && (
                                <p><span className="font-medium">Total Peserta:</span> {matchmakingData.summary.total_participants}</p>
                            )}
                        </div>
                    </div>
                )}

                {/* Waiting Queue for specific event */}
                {eventId && matchmakingData.waiting_queue && matchmakingData.waiting_queue.length > 0 && (
                    <div className="mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                            <div className="w-3 h-3 bg-yellow-500 rounded-full mr-2"></div>
                            Antrian Pemain ({matchmakingData.waiting_queue.length})
                        </h2>
                        <div className="bg-white rounded-lg shadow-sm border border-gray-200">
                            {matchmakingData.waiting_queue.map((player, index) => (
                                <div key={player.user_id} className="flex items-center justify-between p-4 border-b border-gray-100 last:border-b-0">
                                    <div className="flex items-center space-x-3">
                                        <div className="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center">
                                            <span className="text-sm font-medium text-yellow-700">
                                                {index + 1}
                                            </span>
                                        </div>
                                        <div>
                                            <p className="font-medium text-gray-900">{player.name}</p>
                                            <p className="text-sm text-gray-500">
                                                Menunggu sejak: {new Date(player.waiting_since).toLocaleTimeString('id-ID')}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )}

                {/* Ongoing Matches */}
                {matchmakingData.ongoing_matches.length > 0 && (
                    <div className="mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                            <div className="w-3 h-3 bg-primary rounded-full mr-2"></div>
                            Pertandingan Berlangsung
                        </h2>
                        {matchmakingData.ongoing_matches.map((match) => (
                            <div key={match.id} className="bg-white rounded-lg p-4 mb-3 shadow-sm border border-gray-200">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium text-primary">
                                        {match.event.sport.name} • Court {match.court_number}
                                    </span>
                                    <span className="bg-primary text-white text-xs px-2 py-1 rounded-full">
                                        LIVE
                                    </span>
                                </div>
                                <h3 className="font-semibold text-gray-900 mb-2">{match.event.title}</h3>
                                <div className="flex justify-between items-center">
                                    <div className="text-sm text-gray-600">
                                        <div>{match.player1.name} vs {match.player2.name}</div>
                                        <div className="text-xs text-gray-500 mt-1">
                                            {formatDateTime(match.match_date)}
                                        </div>
                                    </div>
                                    <div className="text-lg font-bold text-primary">
                                        {match.player1_score || 0} - {match.player2_score || 0}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Scheduled Matches */}
                {matchmakingData.scheduled_matches.length > 0 && (
                    <div className="mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                            <div className="w-3 h-3 bg-blue-600 rounded-full mr-2"></div>
                            Pertandingan Terjadwal
                        </h2>
                        {matchmakingData.scheduled_matches.map((match) => (
                            <div key={match.id} className="bg-white rounded-lg p-4 mb-3 shadow-sm border border-gray-200">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium text-blue-600">
                                        {match.event.sport.name} • Court {match.court_number}
                                    </span>
                                    <span className="bg-blue-100 text-blue-600 text-xs px-2 py-1 rounded-full">
                                        TERJADWAL
                                    </span>
                                </div>
                                <h3 className="font-semibold text-gray-900 mb-2">{match.event.title}</h3>
                                <div className="flex justify-between items-center">
                                    <div className="text-sm text-gray-600">
                                        <div>{match.player1.name} vs {match.player2.name}</div>
                                        <div className="text-xs text-gray-500 mt-1">
                                            {formatDateTime(match.match_date)}
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="text-xs text-gray-500">Estimasi</div>
                                        <div className="text-sm font-medium">{match.estimated_duration || 60} menit</div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Completed Matches for event-specific view */}
                {eventId && matchmakingData.completed_matches && matchmakingData.completed_matches.length > 0 && (
                    <div className="mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                            <div className="w-3 h-3 bg-gray-600 rounded-full mr-2"></div>
                            Pertandingan Selesai
                        </h2>
                        {matchmakingData.completed_matches.map((match) => (
                            <div key={match.id} className="bg-white rounded-lg p-4 mb-3 shadow-sm border border-gray-200">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium text-gray-600">
                                        {match.event.sport.name} • Court {match.court_number || 'N/A'}
                                    </span>
                                    <span className="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded-full">
                                        SELESAI
                                    </span>
                                </div>
                                <h3 className="font-semibold text-gray-900 mb-2">{match.event.title}</h3>
                                <div className="flex justify-between items-center">
                                    <div className="text-sm text-gray-600">
                                        <div>{match.player1.name} vs {match.player2.name}</div>
                                        <div className="text-xs text-gray-500 mt-1">
                                            {formatDateTime(match.match_date)}
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        {match.match_score && (
                                            <div className="text-lg font-bold text-gray-700">
                                                {match.match_score.player1_score || 0} - {match.match_score.player2_score || 0}
                                            </div>
                                        )}
                                        <div className="text-xs text-gray-500 mt-1">
                                            {match.result === 'player1_win' ? `${match.player1.name} Menang` :
                                             match.result === 'player2_win' ? `${match.player2.name} Menang` :
                                             match.result === 'draw' ? 'Seri' : 'Belum Ada Hasil'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Upcoming Events */}
                {!eventId && matchmakingData.upcoming_events && matchmakingData.upcoming_events.length > 0 && (
                    <div className="mb-6">
                        <h2 className="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                            <div className="w-3 h-3 bg-green-600 rounded-full mr-2"></div>
                            Event Mendatang
                        </h2>
                        {matchmakingData.upcoming_events.map((eventData) => (
                            <div key={eventData.event.id} className="bg-white rounded-lg p-4 mb-3 shadow-sm border border-gray-200">
                                <div className="flex items-center justify-between mb-2">
                                    <span className="text-sm font-medium text-green-600">
                                        {eventData.event.sport.name}
                                    </span>
                                    <span className={`text-xs px-2 py-1 rounded-full ${
                                        eventData.has_matchmaking 
                                            ? 'bg-green-100 text-green-600' 
                                            : eventData.can_start_matchmaking 
                                                ? 'bg-yellow-100 text-yellow-600'
                                                : 'bg-gray-100 text-gray-600'
                                    }`}>
                                        {eventData.status}
                                    </span>
                                </div>
                                <h3 className="font-semibold text-gray-900 mb-2">{eventData.event.title}</h3>
                                <div className="flex justify-between items-center">
                                    <div className="text-sm text-gray-600">
                                        <div>{eventData.confirmed_participants} peserta terkonfirmasi</div>
                                        <div className="text-xs text-gray-500 mt-1">
                                            {formatDateTime(eventData.event.event_date)}
                                        </div>
                                    </div>
                                    <button 
                                        onClick={() => onNavigate('eventDetail', { eventId: eventData.event.id })}
                                        className="text-primary text-sm font-medium hover:text-primary-dark"
                                    >
                                        Lihat Detail
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {/* Empty State */}
                {matchmakingData.ongoing_matches.length === 0 && 
                 matchmakingData.scheduled_matches.length === 0 && 
                 matchmakingData.upcoming_events.length === 0 && (
                    <div className="text-center py-12">
                        <div className="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <svg className="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" />
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.879 16.121A3 3 0 1012.015 11L11 14H9c0 .768.293 1.536.879 2.121z" />
                            </svg>
                        </div>
                        <h3 className="font-semibold text-gray-900 mb-2">Belum Ada Matchmaking</h3>
                        <p className="text-gray-600 mb-4">
                            Ikuti event dan dapatkan matchmaking otomatis dengan peserta lain
                        </p>
                        <button 
                            onClick={() => onNavigate('discover')}
                            className="bg-primary text-white px-6 py-2 rounded-lg hover:bg-primary-dark transition-colors"
                        >
                            Jelajahi Event
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
};

export default MatchmakingStatusPage; 