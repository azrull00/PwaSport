import React, { useState, useEffect, useCallback } from 'react';
import { toast } from 'react-hot-toast';
import axios from 'axios';
import { HiRefresh, HiUserAdd, HiUsers, HiClock, HiLocationMarker } from 'react-icons/hi';
import GuestPlayerManagement from './GuestPlayerManagement';

const MatchmakingDashboard = ({ eventId }) => {
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
        if (loading && !forceRefresh) return;
        
        setLoading(!forceRefresh);
        setRefreshing(forceRefresh);
        try {
            const response = await axios.get(`/api/matchmaking/${eventId}/status`);
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
        fetchMatchmakingStatus();
        const interval = setInterval(() => fetchMatchmakingStatus(true), 30000);
        return () => clearInterval(interval);
    }, [fetchMatchmakingStatus]);

    const handleCreateMatches = async () => {
        try {
            const response = await axios.post(`/api/matchmaking/${eventId}/fair-matches`);
            if (response.data.success) {
                toast.success('New matches created successfully');
                fetchMatchmakingStatus(true);
            }
        } catch (error) {
            toast.error('Failed to create matches');
        }
    };

    const handleOverrideMatch = async (matchId, playerId, replacementId) => {
        try {
            await axios.post(`/api/matchmaking/${eventId}/override-player`, {
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
        try {
            await axios.post(`/api/matchmaking/${eventId}/assign-court`, {
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
            <div className="flex flex-col">
                <div className="flex items-center space-x-2">
                    <img
                        src={player.profile_picture || '/default-avatar.png'}
                        alt={player.name}
                        className="w-8 h-8 rounded-full"
                    />
                    <div>
                        <span className="font-medium text-gray-900">{player.name}</span>
                        {isGuest && (
                            <span className="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                Guest
                            </span>
                        )}
                        <div className="flex items-center space-x-2 text-sm text-gray-500">
                            <span>MMR: {player.mmr}</span>
                            {!isGuest && (
                                <span>• Win Rate: {player.win_rate ? `${(player.win_rate * 100).toFixed(1)}%` : 'N/A'}</span>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        );
    };

    if (loading) {
        return (
            <div className="flex items-center justify-center min-h-[400px]">
                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500"></div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header Actions */}
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center space-x-4">
                    <h1 className="text-2xl font-bold text-gray-900">Matchmaking Dashboard</h1>
                    <button
                        onClick={() => fetchMatchmakingStatus(true)}
                        className={`p-2 text-gray-500 hover:text-gray-700 rounded-full hover:bg-gray-100 ${
                            refreshing ? 'animate-spin' : ''
                        }`}
                    >
                        <HiRefresh className="w-5 h-5" />
                    </button>
                </div>
                <div className="flex space-x-3">
                    <button
                        onClick={() => setShowGuestManagement(!showGuestManagement)}
                        className="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"
                    >
                        <HiUserAdd className="w-5 h-5 mr-2" />
                        {showGuestManagement ? 'Hide Guest Management' : 'Manage Guests'}
                    </button>
                    <button
                        onClick={handleCreateMatches}
                        className="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700"
                    >
                        <HiUsers className="w-5 h-5 mr-2" />
                        Create New Matches
                    </button>
                </div>
            </div>

            {/* Guest Player Management */}
            {showGuestManagement && (
                <div className="bg-white shadow sm:rounded-lg overflow-hidden">
                    <div className="px-4 py-5 sm:p-6">
                        <GuestPlayerManagement 
                            eventId={eventId} 
                            onGuestAdded={() => fetchMatchmakingStatus(true)} 
                        />
                    </div>
                </div>
            )}

            {/* Waiting Players */}
            <div className="bg-white shadow sm:rounded-lg overflow-hidden">
                <div className="px-4 py-5 sm:p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-medium text-gray-900">
                            Waiting Players ({waitingPlayers.length})
                        </h2>
                        <span className="text-sm text-gray-500">
                            <HiClock className="inline w-4 h-4 mr-1" />
                            Auto-refreshes every 30s
                        </span>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        {waitingPlayers.map((player) => (
                            <div key={player.id} className="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                {renderPlayerStats(player)}
                                <div className="mt-2 text-sm text-gray-500">
                                    <HiClock className="inline w-4 h-4 mr-1" />
                                    Waiting: {player.waiting_minutes} mins
                                </div>
                            </div>
                        ))}
                        {waitingPlayers.length === 0 && (
                            <div className="col-span-full text-center py-8 text-gray-500">
                                No players waiting for matches
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* Current Matches */}
            <div className="bg-white shadow sm:rounded-lg overflow-hidden">
                <div className="px-4 py-5 sm:p-6">
                    <h2 className="text-lg font-medium text-gray-900 mb-4">
                        Current Matches ({matches.length})
                    </h2>
                    <div className="space-y-4">
                        {matches.map((match) => (
                            <div key={match.id} className="bg-gray-50 p-4 rounded-lg border border-gray-200">
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div className="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                                        {renderPlayerStats(match.player1)}
                                        <div className="text-gray-500 font-medium px-4">vs</div>
                                        {renderPlayerStats(match.player2)}
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        {match.court_number ? (
                                            <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                                <HiLocationMarker className="w-4 h-4 mr-1" />
                                                Court {match.court_number}
                                            </span>
                                        ) : (
                                            <select
                                                onChange={(e) => handleAssignCourt(match.id, e.target.value)}
                                                className="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                            >
                                                <option value="">Assign Court</option>
                                                {[...Array(10)].map((_, i) => (
                                                    <option key={i + 1} value={i + 1}>
                                                        Court {i + 1}
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                        <button
                                            onClick={() => {
                                                setSelectedMatch(match);
                                                setIsOverriding(true);
                                            }}
                                            className="inline-flex items-center px-3 py-1 border border-transparent text-sm font-medium rounded-md text-blue-700 bg-blue-100 hover:bg-blue-200"
                                        >
                                            Override
                                        </button>
                                    </div>
                                </div>

                                {/* Override Player UI */}
                                {isOverriding && selectedMatch?.id === match.id && (
                                    <div className="mt-4 p-4 border rounded-lg bg-white">
                                        <h4 className="font-medium mb-4">Override Player</h4>
                                        <div className="space-y-4">
                                            <div>
                                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                                    Select Player to Replace
                                                </label>
                                                <select
                                                    value={overridePlayer?.id || ''}
                                                    onChange={(e) => {
                                                        const player = match.player1.id === e.target.value 
                                                            ? match.player1 
                                                            : match.player2;
                                                        setOverridePlayer(player);
                                                    }}
                                                    className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                >
                                                    <option value="">Select player</option>
                                                    <option value={match.player1.id}>{match.player1.name}</option>
                                                    <option value={match.player2.id}>{match.player2.name}</option>
                                                </select>
                                            </div>

                                            {overridePlayer && (
                                                <div>
                                                    <label className="block text-sm font-medium text-gray-700 mb-1">
                                                        Select Replacement
                                                    </label>
                                                    <select
                                                        onChange={(e) => handleOverrideMatch(
                                                            match.id,
                                                            overridePlayer.id,
                                                            e.target.value
                                                        )}
                                                        className="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                                    >
                                                        <option value="">Select replacement</option>
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
                                                    className="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                                                >
                                                    Cancel Override
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                        {matches.length === 0 && (
                            <div className="text-center py-8 text-gray-500">
                                No active matches
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default MatchmakingDashboard; 