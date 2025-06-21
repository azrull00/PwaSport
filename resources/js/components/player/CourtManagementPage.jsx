import React, { useState, useEffect } from 'react';

const CourtManagementPage = ({ eventId, userToken, userType, onNavigate, onBack }) => {
    const [courtData, setCourtData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [refreshInterval, setRefreshInterval] = useState(null);
    const [isHost, setIsHost] = useState(false);
    const [eventInfo, setEventInfo] = useState(null);

    useEffect(() => {
        if (eventId && userToken) {
            loadCourtData();
            // Auto refresh every 30 seconds for real-time updates
            const interval = setInterval(loadCourtData, 30000);
            setRefreshInterval(interval);
            
            return () => {
                if (interval) clearInterval(interval);
            };
        }
    }, [eventId, userToken]);

    const loadCourtData = async () => {
        try {
            setError('');
            
            const response = await fetch(`/api/matchmaking/${eventId}/court-status`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                setCourtData(data.data.court_status);
                setEventInfo({
                    id: data.data.event_id,
                    title: data.data.event_title
                });
                
                // Check if user is host (only hosts can manage courts)
                setIsHost(response.status !== 403);
            } else {
                setError(data.message || 'Gagal memuat data court');
                setIsHost(false);
            }
        } catch (error) {
            console.error('Error loading court data:', error);
            setError('Terjadi kesalahan saat memuat data');
        } finally {
            setLoading(false);
        }
    };

    const assignCourt = async (courtNumber, player1Id, player2Id) => {
        try {
            const response = await fetch(`/api/matchmaking/${eventId}/assign-court`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    court_number: courtNumber,
                    player1_id: player1Id,
                    player2_id: player2Id
                })
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                alert('Pemain berhasil ditempatkan di court!');
                loadCourtData(); // Refresh data
            } else {
                alert(data.message || 'Gagal menempatkan pemain');
            }
        } catch (error) {
            console.error('Error assigning court:', error);
            alert('Terjadi kesalahan saat menempatkan pemain');
        }
    };

    const startMatch = async (matchId) => {
        try {
            const response = await fetch(`/api/matchmaking/${eventId}/start-match/${matchId}`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                alert('Match dimulai!');
                loadCourtData(); // Refresh data
            } else {
                alert(data.message || 'Gagal memulai match');
            }
        } catch (error) {
            console.error('Error starting match:', error);
            alert('Terjadi kesalahan saat memulai match');
        }
    };

    const overridePlayer = async (matchId, oldPlayerId, newPlayerId, reason = '') => {
        try {
            const response = await fetch(`/api/matchmaking/${eventId}/override-player`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    match_id: matchId,
                    old_player_id: oldPlayerId,
                    new_player_id: newPlayerId,
                    reason: reason
                })
            });

            const data = await response.json();
            
            if (data.status === 'success') {
                alert('Pemain berhasil diganti!');
                loadCourtData(); // Refresh data
            } else {
                alert(data.message || 'Gagal mengganti pemain');
            }
        } catch (error) {
            console.error('Error overriding player:', error);
            alert('Terjadi kesalahan saat mengganti pemain');
        }
    };

    const formatTime = (dateString) => {
        return new Date(dateString).toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const formatWaitingTime = (minutes) => {
        if (minutes < 60) {
            return `${minutes} menit`;
        }
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        return `${hours}j ${remainingMinutes}m`;
    };

    const CourtCard = ({ court }) => {
        const getStatusColor = (status) => {
            switch (status) {
                case 'available': return 'bg-green-100 text-green-700 border-green-200';
                case 'playing': return 'bg-red-100 text-red-700 border-red-200';
                case 'scheduled': return 'bg-yellow-100 text-yellow-700 border-yellow-200';
                default: return 'bg-gray-100 text-gray-700 border-gray-200';
            }
        };

        const getStatusText = (status) => {
            switch (status) {
                case 'available': return 'Tersedia';
                case 'playing': return 'Sedang Bermain';
                case 'scheduled': return 'Dijadwalkan';
                default: return 'Unknown';
            }
        };

        return (
            <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                <div className="flex items-center justify-between mb-3">
                    <h3 className="text-lg font-semibold text-gray-900">
                        Court {court.court_number}
                    </h3>
                    <span className={`px-3 py-1 rounded-full text-sm font-medium border ${getStatusColor(court.status)}`}>
                        {getStatusText(court.status)}
                    </span>
                </div>

                {court.match ? (
                    <div className="space-y-3">
                        <div className="flex items-center space-x-3">
                            <div className="flex items-center space-x-2">
                                <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                                    <span className="text-sm font-medium text-blue-700">
                                        {court.match.player1.name.charAt(0)}
                                    </span>
                                </div>
                                <span className="text-sm font-medium text-gray-900">
                                    {court.match.player1.name}
                                </span>
                            </div>
                            <span className="text-gray-400">vs</span>
                            <div className="flex items-center space-x-2">
                                <div className="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                                    <span className="text-sm font-medium text-green-700">
                                        {court.match.player2.name.charAt(0)}
                                    </span>
                                </div>
                                <span className="text-sm font-medium text-gray-900">
                                    {court.match.player2.name}
                                </span>
                            </div>
                        </div>

                        <div className="text-sm text-gray-600">
                            <p>Mulai: {formatTime(court.match.start_time)}</p>
                            <p>Durasi: {court.match.estimated_duration} menit</p>
                            {court.match.current_score && (
                                <p>Score: {JSON.stringify(court.match.current_score)}</p>
                            )}
                        </div>

                        {isHost && court.status === 'scheduled' && (
                            <button
                                onClick={() => startMatch(court.match.id)}
                                className="w-full bg-primary text-white py-2 rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors"
                            >
                                Mulai Match
                            </button>
                        )}
                    </div>
                ) : (
                    <div className="text-center py-6">
                        <div className="text-gray-400 text-4xl mb-2">🏸</div>
                        <p className="text-gray-500 text-sm">Court kosong</p>
                        {isHost && (
                            <p className="text-xs text-gray-400 mt-1">
                                Assign pemain dari antrian
                            </p>
                        )}
                    </div>
                )}
            </div>
        );
    };

    const QueueCard = ({ queue }) => {
        const [selectedPlayers, setSelectedPlayers] = useState([]);
        const [selectedCourt, setSelectedCourt] = useState('');

        const handlePlayerSelect = (playerId) => {
            if (selectedPlayers.includes(playerId)) {
                setSelectedPlayers(selectedPlayers.filter(id => id !== playerId));
            } else if (selectedPlayers.length < 2) {
                setSelectedPlayers([...selectedPlayers, playerId]);
            }
        };

        const handleAssignCourt = () => {
            if (selectedPlayers.length === 2 && selectedCourt) {
                assignCourt(parseInt(selectedCourt), selectedPlayers[0], selectedPlayers[1]);
                setSelectedPlayers([]);
                setSelectedCourt('');
            }
        };

        const availableCourts = courtData?.courts?.filter(court => court.status === 'available') || [];

        return (
            <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-semibold text-gray-900">
                        Antrian Pemain ({queue.total_waiting})
                    </h3>
                    {queue.can_create_match && (
                        <span className="px-2 py-1 bg-green-100 text-green-700 rounded-full text-xs font-medium">
                            Siap Match
                        </span>
                    )}
                </div>

                {queue.waiting_players && queue.waiting_players.length > 0 ? (
                    <div className="space-y-3">
                        {queue.waiting_players.map((player) => (
                            <div 
                                key={player.user_id} 
                                className={`p-3 rounded-lg border cursor-pointer transition-colors ${
                                    selectedPlayers.includes(player.user_id)
                                        ? 'border-primary bg-primary-light'
                                        : 'border-gray-200 hover:border-gray-300'
                                } ${!isHost ? 'cursor-default' : ''}`}
                                onClick={() => isHost && handlePlayerSelect(player.user_id)}
                            >
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center space-x-3">
                                        <div className="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span className="text-sm font-medium text-blue-700">
                                                {player.name.charAt(0)}
                                            </span>
                                        </div>
                                        <div>
                                            <p className="font-medium text-gray-900">
                                                {player.name}
                                                {player.is_premium && (
                                                    <span className="ml-2 px-2 py-0.5 bg-yellow-100 text-yellow-700 rounded-full text-xs">
                                                        Premium
                                                    </span>
                                                )}
                                            </p>
                                            <div className="flex items-center space-x-3 text-sm text-gray-600">
                                                <span>MMR: {player.mmr}</span>
                                                <span>Level: {player.level}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div className="text-right text-sm text-gray-500">
                                        <p>Menunggu</p>
                                        <p>{formatWaitingTime(player.waiting_minutes)}</p>
                                    </div>
                                </div>
                            </div>
                        ))}

                        {isHost && selectedPlayers.length === 2 && availableCourts.length > 0 && (
                            <div className="mt-4 p-3 bg-gray-50 rounded-lg">
                                <p className="text-sm font-medium text-gray-900 mb-2">
                                    Assign ke Court:
                                </p>
                                <div className="flex items-center space-x-2">
                                    <select
                                        value={selectedCourt}
                                        onChange={(e) => setSelectedCourt(e.target.value)}
                                        className="flex-1 px-3 py-2 border border-gray-300 rounded-lg text-sm"
                                    >
                                        <option value="">Pilih Court</option>
                                        {availableCourts.map((court) => (
                                            <option key={court.court_number} value={court.court_number}>
                                                Court {court.court_number}
                                            </option>
                                        ))}
                                    </select>
                                    <button
                                        onClick={handleAssignCourt}
                                        disabled={!selectedCourt}
                                        className="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary-dark transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                    >
                                        Assign
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="text-center py-6">
                        <div className="text-gray-400 text-4xl mb-2">⏳</div>
                        <p className="text-gray-500">Tidak ada pemain dalam antrian</p>
                    </div>
                )}
            </div>
        );
    };

    if (loading) {
        return (
            <div className="bg-secondary min-h-screen">
                <div className="bg-white border-b border-gray-100 px-4 py-3">
                    <div className="flex items-center justify-between">
                        <button
                            onClick={onBack}
                            className="flex items-center text-gray-600 hover:text-gray-800"
                        >
                            <span className="mr-2">←</span>
                            Kembali
                        </button>
                        <h1 className="text-lg font-semibold text-gray-900">Court Management</h1>
                        <div></div>
                    </div>
                </div>
                
                <div className="flex items-center justify-center h-64">
                    <div className="text-center">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto"></div>
                        <p className="text-gray-600 mt-2">Memuat data court...</p>
                    </div>
                </div>
            </div>
        );
    }

    if (error) {
        return (
            <div className="bg-secondary min-h-screen">
                <div className="bg-white border-b border-gray-100 px-4 py-3">
                    <div className="flex items-center justify-between">
                        <button
                            onClick={onBack}
                            className="flex items-center text-gray-600 hover:text-gray-800"
                        >
                            <span className="mr-2">←</span>
                            Kembali
                        </button>
                        <h1 className="text-lg font-semibold text-gray-900">Court Management</h1>
                        <div></div>
                    </div>
                </div>
                
                <div className="p-4">
                    <div className="bg-red-50 border border-red-200 rounded-xl p-4">
                        <p className="text-red-700">{error}</p>
                        <button
                            onClick={loadCourtData}
                            className="mt-3 text-red-600 hover:text-red-800 text-sm font-medium"
                        >
                            Coba lagi
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="bg-secondary min-h-screen">
            {/* Header */}
            <div className="bg-white border-b border-gray-100 px-4 py-3">
                <div className="flex items-center justify-between">
                    <button
                        onClick={onBack}
                        className="flex items-center text-gray-600 hover:text-gray-800"
                    >
                        <span className="mr-2">←</span>
                        Kembali
                    </button>
                    <div className="text-center">
                        <h1 className="text-lg font-semibold text-gray-900">Court Management</h1>
                        <p className="text-sm text-gray-600">{eventInfo?.title}</p>
                    </div>
                    <button
                        onClick={loadCourtData}
                        className="text-primary hover:text-primary-dark"
                    >
                        🔄
                    </button>
                </div>
            </div>

            {/* Content */}
            <div className="p-4 pb-20">
                {/* Stats Overview */}
                <div className="grid grid-cols-3 gap-4 mb-6">
                    <div className="bg-white rounded-xl p-3 text-center shadow-sm">
                        <div className="text-xl font-bold text-green-600">
                            {courtData?.courts?.filter(c => c.status === 'available').length || 0}
                        </div>
                        <div className="text-xs text-gray-600">Court Kosong</div>
                    </div>
                    <div className="bg-white rounded-xl p-3 text-center shadow-sm">
                        <div className="text-xl font-bold text-red-600">
                            {courtData?.active_matches || 0}
                        </div>
                        <div className="text-xs text-gray-600">Sedang Bermain</div>
                    </div>
                    <div className="bg-white rounded-xl p-3 text-center shadow-sm">
                        <div className="text-xl font-bold text-blue-600">
                            {courtData?.queue?.total_waiting || 0}
                        </div>
                        <div className="text-xs text-gray-600">Dalam Antrian</div>
                    </div>
                </div>

                {/* Courts Grid */}
                <div className="mb-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">Status Court</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {courtData?.courts?.map((court) => (
                            <CourtCard key={court.court_number} court={court} />
                        ))}
                    </div>
                </div>

                {/* Queue Management */}
                <div className="mb-6">
                    <h2 className="text-lg font-semibold text-gray-900 mb-4">
                        Antrian Pemain
                        {!isHost && (
                            <span className="ml-2 text-sm text-gray-500 font-normal">
                                (View Only)
                            </span>
                        )}
                    </h2>
                    {courtData?.queue && (
                        <QueueCard queue={courtData.queue} />
                    )}
                </div>

                {/* Host Actions */}
                {isHost && (
                    <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                        <h3 className="text-lg font-semibold text-gray-900 mb-3">Host Actions</h3>
                        <div className="grid grid-cols-2 gap-3">
                            <button
                                onClick={() => {
                                    // TODO: Implement next round suggestions
                                    alert('Next round suggestions coming soon!');
                                }}
                                className="bg-blue-500 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-blue-600 transition-colors"
                            >
                                Saran Round Berikutnya
                            </button>
                            <button
                                onClick={() => onNavigate('matchmakingStatus')}
                                className="bg-green-500 text-white py-2 px-4 rounded-lg text-sm font-medium hover:bg-green-600 transition-colors"
                            >
                                Lihat Match History
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
};

export default CourtManagementPage; 