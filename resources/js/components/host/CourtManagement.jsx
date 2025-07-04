import React, { useState, useEffect, useCallback } from 'react';
import { toast } from 'react-hot-toast';
import { HiRefresh, HiClock, HiLocationMarker, HiPlay, HiStop } from 'react-icons/hi';
import axios from 'axios';

const CourtManagement = ({ eventId, userToken, onNavigate }) => {
    const [courts, setCourts] = useState([]);
    const [matches, setMatches] = useState([]);
    const [loading, setLoading] = useState(true);
    const [refreshing, setRefreshing] = useState(false);
    const [error, setError] = useState(null);
    const [eventInfo, setEventInfo] = useState(null);

    const fetchCourtData = useCallback(async (showLoading = false) => {
        if (showLoading) setRefreshing(true);
        try {
            const response = await axios.get(`/api/matchmaking/${eventId}/court-status`, {
                headers: {
                    'Authorization': `Bearer ${userToken}`,
                    'Accept': 'application/json'
                }
            });

            if (response.data.status === 'success') {
                setCourts(response.data.data.courts);
                setMatches(response.data.data.matches);
                setEventInfo({
                    id: response.data.data.event_id,
                    title: response.data.data.event_title
                });
                setError(null);
            } else {
                setError(response.data.message || 'Failed to load court data');
            }
        } catch (error) {
            console.error('Error loading court data:', error);
            setError('An error occurred while loading data');
            toast.error('Failed to load court data');
        } finally {
            setLoading(false);
            setRefreshing(false);
        }
    }, [eventId, userToken]);

    useEffect(() => {
        if (eventId && userToken) {
            fetchCourtData(true);
            const interval = setInterval(() => fetchCourtData(false), 30000);
            return () => clearInterval(interval);
        }
    }, [fetchCourtData, eventId, userToken]);

    const handleStartMatch = async (matchId) => {
        try {
            const response = await axios.post(
                `/api/matchmaking/${eventId}/start-match/${matchId}`,
                {},
                {
                    headers: {
                        'Authorization': `Bearer ${userToken}`,
                        'Accept': 'application/json'
                    }
                }
            );

            if (response.data.status === 'success') {
                toast.success('Match started successfully');
                fetchCourtData(true);
            } else {
                toast.error(response.data.message || 'Failed to start match');
            }
        } catch (error) {
            console.error('Error starting match:', error);
            toast.error('Failed to start match');
        }
    };

    const handleEndMatch = async (matchId) => {
        try {
            const response = await axios.post(
                `/api/matchmaking/${eventId}/end-match/${matchId}`,
                {},
                {
                    headers: {
                        'Authorization': `Bearer ${userToken}`,
                        'Accept': 'application/json'
                    }
                }
            );

            if (response.data.status === 'success') {
                toast.success('Match ended successfully');
                fetchCourtData(true);
            } else {
                toast.error(response.data.message || 'Failed to end match');
            }
        } catch (error) {
            console.error('Error ending match:', error);
            toast.error('Failed to end match');
        }
    };

    const handleAssignCourt = async (courtNumber, matchId) => {
        try {
            const response = await axios.post(
                `/api/matchmaking/${eventId}/assign-court`,
                {
                    court_number: courtNumber,
                    match_id: matchId
                },
                {
                    headers: {
                        'Authorization': `Bearer ${userToken}`,
                        'Accept': 'application/json'
                    }
                }
            );

            if (response.data.status === 'success') {
                toast.success('Court assigned successfully');
                fetchCourtData(true);
            } else {
                toast.error(response.data.message || 'Failed to assign court');
            }
        } catch (error) {
            console.error('Error assigning court:', error);
            toast.error('Failed to assign court');
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
            return `${minutes} minutes`;
        }
        const hours = Math.floor(minutes / 60);
        const remainingMinutes = minutes % 60;
        return `${hours}h ${remainingMinutes}m`;
    };

    const CourtCard = ({ court }) => {
        const getStatusColor = (status) => {
            switch (status) {
                case 'available': return 'bg-green-100 text-green-700 border-green-200';
                case 'playing': return 'bg-red-100 text-red-700 border-red-200';
                case 'scheduled': return 'bg-yellow-100 text-yellow-700 border-yellow-200';
                case 'maintenance': return 'bg-gray-100 text-gray-700 border-gray-200';
                default: return 'bg-gray-100 text-gray-700 border-gray-200';
            }
        };

        const getStatusText = (status) => {
            switch (status) {
                case 'available': return 'Available';
                case 'playing': return 'In Progress';
                case 'scheduled': return 'Scheduled';
                case 'maintenance': return 'Maintenance';
                default: return 'Unknown';
            }
        };

        return (
            <div className="bg-white rounded-xl p-4 shadow-sm border border-gray-100">
                <div className="flex items-center justify-between mb-3">
                    <h3 className="text-lg font-semibold text-gray-900">
                        Court {court.number}
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
                            <p>Start: {formatTime(court.match.start_time)}</p>
                            <p>Duration: {court.match.estimated_duration} minutes</p>
                            {court.match.current_score && (
                                <p>Score: {JSON.stringify(court.match.current_score)}</p>
                            )}
                        </div>

                        {court.status === 'scheduled' ? (
                            <button
                                onClick={() => handleStartMatch(court.match.id)}
                                className="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                            >
                                <HiPlay className="w-5 h-5 mr-2" />
                                Start Match
                            </button>
                        ) : court.status === 'playing' && (
                            <button
                                onClick={() => handleEndMatch(court.match.id)}
                                className="w-full inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                            >
                                <HiStop className="w-5 h-5 mr-2" />
                                End Match
                            </button>
                        )}
                    </div>
                ) : (
                    <div className="text-center py-6">
                        <div className="text-gray-400 text-4xl mb-2">🏸</div>
                        <p className="text-gray-500 text-sm">Court available</p>
                        {matches.filter(m => !m.court_number).length > 0 && (
                            <select
                                onChange={(e) => handleAssignCourt(court.number, e.target.value)}
                                className="mt-4 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                defaultValue=""
                            >
                                <option value="" disabled>Assign a match...</option>
                                {matches
                                    .filter(m => !m.court_number)
                                    .map(match => (
                                        <option key={match.id} value={match.id}>
                                            {match.player1.name} vs {match.player2.name}
                                        </option>
                                    ))
                                }
                            </select>
                        )}
                    </div>
                )}
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
            {/* Header */}
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center space-x-4">
                    <h1 className="text-2xl font-bold text-gray-900">Court Management</h1>
                    <button
                        onClick={() => fetchCourtData(true)}
                        className={`p-2 text-gray-500 hover:text-gray-700 rounded-full hover:bg-gray-100 ${
                            refreshing ? 'animate-spin' : ''
                        }`}
                    >
                        <HiRefresh className="w-5 h-5" />
                    </button>
                </div>
                {eventInfo && (
                    <div className="text-sm text-gray-500">
                        Event: {eventInfo.title}
                    </div>
                )}
            </div>

            {error && (
                <div className="rounded-md bg-red-50 p-4">
                    <div className="flex">
                        <div className="ml-3">
                            <h3 className="text-sm font-medium text-red-800">Error</h3>
                            <div className="mt-2 text-sm text-red-700">{error}</div>
                        </div>
                    </div>
                </div>
            )}

            {/* Courts Grid */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {courts.map(court => (
                    <CourtCard key={court.number} court={court} />
                ))}
            </div>

            {/* Waiting Matches */}
            <div className="bg-white shadow sm:rounded-lg overflow-hidden">
                <div className="px-4 py-5 sm:p-6">
                    <div className="flex items-center justify-between mb-4">
                        <h2 className="text-lg font-medium text-gray-900">
                            Waiting Matches ({matches.filter(m => !m.court_number).length})
                        </h2>
                        <span className="text-sm text-gray-500">
                            <HiClock className="inline w-4 h-4 mr-1" />
                            Auto-refreshes every 30s
                        </span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Players
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Waiting Time
                                    </th>
                                    <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        MMR Difference
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {matches
                                    .filter(m => !m.court_number)
                                    .map(match => (
                                        <tr key={match.id}>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <div className="flex items-center space-x-3">
                                                    <div className="flex-shrink-0 h-8 w-8">
                                                        <div className="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center">
                                                            <span className="text-sm font-medium text-blue-700">
                                                                {match.player1.name.charAt(0)}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {match.player1.name}
                                                    </div>
                                                    <div className="text-sm text-gray-500">vs</div>
                                                    <div className="flex-shrink-0 h-8 w-8">
                                                        <div className="h-8 w-8 rounded-full bg-green-100 flex items-center justify-center">
                                                            <span className="text-sm font-medium text-green-700">
                                                                {match.player2.name.charAt(0)}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {match.player2.name}
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {formatWaitingTime(match.waiting_minutes)}
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {Math.abs(match.player1.mmr - match.player2.mmr)}
                                            </td>
                                        </tr>
                                    ))}
                                {matches.filter(m => !m.court_number).length === 0 && (
                                    <tr>
                                        <td colSpan={3} className="px-6 py-4 text-center text-sm text-gray-500">
                                            No matches waiting for courts
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default CourtManagement; 