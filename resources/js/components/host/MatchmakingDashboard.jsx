import React, { useState, useEffect, useCallback } from 'react';
import { toast } from 'react-hot-toast';
import axios from 'axios';
import { HiRefresh, HiUserAdd, HiUsers, HiClock, HiLocationMarker } from 'react-icons/hi';
import GuestPlayerManagement from './GuestPlayerManagement';
import { useEvent } from '../../contexts/EventContext';

const MatchmakingDashboard = () => {
    const { currentEvent, loading: eventLoading } = useEvent();
    const [matches, setMatches] = useState([]);
    const [waitingPlayers, setWaitingPlayers] = useState([]);
    const [selectedMatch, setSelectedMatch] = useState(null);
    const [isOverriding, setIsOverriding] = useState(false);
    const [overridePlayer, setOverridePlayer] = useState(null);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [showGuestManagement, setShowGuestManagement] = useState(false);
    const [error, setError] = useState(null);

    const fetchMatchmakingStatus = async (forceRefresh = false) => {
        if (!currentEvent?.id) return;
        if (loading && !forceRefresh) return;
        
        setLoading(!forceRefresh);
        setRefreshing(forceRefresh);
        try {
            const response = await axios.get(`/api/matchmaking/${currentEvent.id}/status`);
            setMatches(response.data.matches || []);
            setWaitingPlayers(response.data.waiting_players || []);
            setError(null);
        } catch (error) {
            console.error('Error fetching matchmaking status:', error);
            setError('Failed to load matchmaking status');
            toast.error('Failed to load matchmaking status');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    };

    useEffect(() => {
        if (currentEvent?.id) {
            fetchMatchmakingStatus();
            const interval = setInterval(() => fetchMatchmakingStatus(true), 30000);
            return () => clearInterval(interval);
        }
    }, [currentEvent?.id]);

    const handleCreateMatches = async () => {
        if (!currentEvent?.id) {
            toast.error('No event selected');
            return;
        }
        try {
            const response = await axios.post(`/api/matchmaking/${currentEvent.id}/fair-matches`);
            if (response.data.success) {
                toast.success('New matches created successfully');
                fetchMatchmakingStatus(true);
            }
        } catch (error) {
            toast.error('Failed to create matches');
        }
    };

    const handleOverrideMatch = async (matchId, playerId, replacementId) => {
        if (!currentEvent?.id) return;
        try {
            await axios.post(`/api/matchmaking/${currentEvent.id}/override-player`, {
                match_id: matchId,
                player_to_replace: playerId,
                replacement_player: replacementId
            });
            toast.success('Player override successful');
            fetchMatchmakingStatus(true);
            setIsOverriding(false);
            setSelectedMatch(null);
            setOverridePlayer(null);
        } catch (error) {
            toast.error('Failed to override player');
        }
    };

    const handleAssignCourt = async (matchId, courtNumber) => {
        if (!currentEvent?.id) return;
        try {
            await axios.post(`/api/matchmaking/${currentEvent.id}/assign-court`, {
                match_id: matchId,
                court_number: courtNumber
            });
            toast.success('Court assigned successfully');
            fetchMatchmakingStatus(true);
        } catch (error) {
            toast.error('Failed to assign court');
        }
    };

    const renderPlayerStats = (player) => {
        if (!player) return null;
        const isGuest = player.is_guest;

        return (
            <div className="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                <img
                    src={player.profile_picture || '/default-avatar.png'}
                    alt={player.name}
                    className="w-10 h-10 rounded-full object-cover"
                />
                <div className="flex-1">
                    <div className="flex items-center space-x-2">
                        <span className="font-medium text-gray-900">{player.name}</span>
                        {isGuest && (
                            <span className="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                Guest
                            </span>
                        )}
                    </div>
                    <div className="flex items-center space-x-2 text-sm text-gray-600">
                        <span>MMR: {player.mmr}</span>
                        {!isGuest && (
                            <span>• Win Rate: {player.win_rate ? `${(player.win_rate * 100).toFixed(1)}%` : 'N/A'}</span>
                        )}
                    </div>
                </div>
            </div>
        );
    };

    if (eventLoading || loading) {
        return (
            <div className="flex items-center justify-center min-h-[400px]">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            </div>
        );
    }

    if (!currentEvent) {
        return (
            <div className="p-4">
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <div className="text-center py-8">
                        <div className="text-6xl mb-4">📅</div>
                        <h3 className="font-semibold text-gray-900 mb-2">Tidak ada event dipilih</h3>
                        <p className="text-gray-600 text-sm">Pilih event untuk mengelola matchmaking</p>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="p-4 space-y-4">
            {/* Header Actions */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                <div className="flex items-center justify-between mb-4">
                    <div className="flex items-center space-x-3">
                        <h1 className="text-lg font-semibold text-gray-900">Matchmaking Dashboard</h1>
                        <button
                            onClick={() => fetchMatchmakingStatus(true)}
                            className={`p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100 ${
                                refreshing ? 'animate-spin' : ''
                            }`}
                        >
                            <HiRefresh className="w-5 h-5" />
                        </button>
                    </div>
                </div>
                <div className="flex flex-col sm:flex-row gap-3">
                    <button
                        onClick={() => setShowGuestManagement(!showGuestManagement)}
                        className="flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                    >
                        <HiUserAdd className="w-5 h-5 mr-2" />
                        {showGuestManagement ? 'Sembunyikan Guest' : 'Kelola Guest'}
                    </button>
                    <button
                        onClick={handleCreateMatches}
                        className="flex items-center justify-center px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors text-sm font-medium"
                    >
                        <HiUsers className="w-5 h-5 mr-2" />
                        Buat Match Baru
                    </button>
                </div>
            </div>

            {/* Guest Player Management */}
            {showGuestManagement && (
                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">Manajemen Guest Player</h3>
                    <GuestPlayerManagement 
                        eventId={currentEvent.id} 
                        onGuestAdded={() => fetchMatchmakingStatus(true)} 
                    />
                </div>
            )}

            {/* Waiting Players */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div className="flex items-center justify-between mb-4">
                    <h2 className="text-lg font-semibold text-gray-900">
                        Player Menunggu ({waitingPlayers.length})
                    </h2>
                    <span className="text-sm text-gray-500 flex items-center">
                        <HiClock className="w-4 h-4 mr-1" />
                        Auto-refresh 30s
                    </span>
                </div>
                <div className="space-y-3">
                    {waitingPlayers.map((player) => (
                        <div key={player.id} className="bg-gray-50 rounded-xl p-4">
                            {renderPlayerStats(player)}
                            <div className="mt-2 flex items-center text-sm text-gray-600">
                                <HiClock className="w-4 h-4 mr-1" />
                                <span>Menunggu: {player.waiting_minutes} menit</span>
                            </div>
                        </div>
                    ))}
                    {waitingPlayers.length === 0 && (
                        <div className="text-center py-8 text-gray-500">
                            <div className="text-6xl mb-4">⏳</div>
                            <p>Tidak ada player yang menunggu</p>
                        </div>
                    )}
                </div>
            </div>

            {/* Current Matches */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 className="text-lg font-semibold text-gray-900 mb-4">
                    Match Aktif ({matches.length})
                </h2>
                <div className="space-y-4">
                    {matches.map((match) => (
                        <div key={match.id} className="bg-gray-50 rounded-xl p-4">
                            <div className="flex flex-col space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center space-x-4">
                                        <div className="flex-1">
                                            {renderPlayerStats(match.player1)}
                                        </div>
                                        <div className="text-gray-500 font-medium px-4">VS</div>
                                        <div className="flex-1">
                                            {renderPlayerStats(match.player2)}
                                        </div>
                                    </div>
                                </div>
                                
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center space-x-2">
                                        {match.court_number ? (
                                            <span className="px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-700 flex items-center">
                                                <HiLocationMarker className="w-4 h-4 mr-1" />
                                                Court {match.court_number}
                                            </span>
                                        ) : (
                                            <select
                                                onChange={(e) => handleAssignCourt(match.id, e.target.value)}
                                                className="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent"
                                            >
                                                <option value="">Pilih Court</option>
                                                {[...Array(10)].map((_, i) => (
                                                    <option key={i + 1} value={i + 1}>
                                                        Court {i + 1}
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                    </div>
                                    <button
                                        onClick={() => {
                                            setSelectedMatch(match);
                                            setIsOverriding(true);
                                        }}
                                        className="px-3 py-1 bg-blue-100 text-blue-700 rounded-lg hover:bg-blue-200 transition-colors text-sm font-medium"
                                    >
                                        Override
                                    </button>
                                </div>

                                {/* Override Player UI */}
                                {isOverriding && selectedMatch?.id === match.id && (
                                    <div className="mt-4 p-4 border border-gray-200 rounded-xl bg-white">
                                        <h4 className="font-medium mb-4">Override Player</h4>
                                        <div className="space-y-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Pilih Player untuk Diganti
                                                </label>
                                                <select
                                                    value={overridePlayer?.id || ''}
                                                    onChange={(e) => {
                                                        const player = match.player1.id === e.target.value 
                                                            ? match.player1 
                                                            : match.player2;
                                                        setOverridePlayer(player);
                                                    }}
                                                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent"
                                                >
                                                    <option value="">Pilih player</option>
                                                    <option value={match.player1.id}>{match.player1.name}</option>
                                                    <option value={match.player2.id}>{match.player2.name}</option>
                                                </select>
                                            </div>

                                            {overridePlayer && (
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                                        Pilih Pengganti
                                                    </label>
                                                    <select
                                                        onChange={(e) => handleOverrideMatch(
                                                            match.id,
                                                            overridePlayer.id,
                                                            e.target.value
                                                        )}
                                                        className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-primary focus:border-transparent"
                                                    >
                                                        <option value="">Pilih pengganti</option>
                                                        {waitingPlayers.map((player) => (
                                                            <option key={player.id} value={player.id}>
                                                                {player.name} (MMR: {player.mmr})
                                                            </option>
                                                        ))}
                                                    </select>
                                                </div>
                                            )}

                                            <div className="flex justify-end">
                                                <button
                                                    onClick={() => {
                                                        setIsOverriding(false);
                                                        setSelectedMatch(null);
                                                        setOverridePlayer(null);
                                                    }}
                                                    className="px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50"
                                                >
                                                    Batal Override
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                    {matches.length === 0 && (
                        <div className="text-center py-8 text-gray-500">
                            <div className="text-6xl mb-4">🎾</div>
                            <p>Tidak ada match aktif</p>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
};

export default MatchmakingDashboard; 