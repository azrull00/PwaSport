import React, { useState, useEffect, useCallback, useMemo } from 'react';
import axios from 'axios';
import Echo from 'laravel-echo';
import { formatDistanceToNow } from 'date-fns';
import { toast } from 'react-hot-toast';

const MatchmakingMonitor = ({ venueId }) => {
  const [matchmakingData, setMatchmakingData] = useState({
    waitingPlayers: [],
    activeMatches: [],
    pendingMatches: []
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [selectedMatch, setSelectedMatch] = useState(null);
  const [showOverrideForm, setShowOverrideForm] = useState(false);
  const [overrideData, setOverrideData] = useState({
    player1_id: '',
    player2_id: '',
    reason: ''
  });
  const [sortConfig, setSortConfig] = useState({ key: 'waiting_since', direction: 'desc' });
  const [lastUpdated, setLastUpdated] = useState(new Date());
  const [retryCount, setRetryCount] = useState(0);

  // Memoized sorted players
  const sortedWaitingPlayers = useMemo(() => {
    return [...matchmakingData.waitingPlayers].sort((a, b) => {
      if (sortConfig.key === 'waiting_since') {
        return sortConfig.direction === 'asc' 
          ? new Date(a.waiting_since) - new Date(b.waiting_since)
          : new Date(b.waiting_since) - new Date(a.waiting_since);
      }
      if (sortConfig.key === 'mmr') {
        return sortConfig.direction === 'asc' ? a.mmr - b.mmr : b.mmr - a.mmr;
      }
      return 0;
    });
  }, [matchmakingData.waitingPlayers, sortConfig]);

  // Fetch matchmaking data with retry logic
  const fetchMatchmakingData = useCallback(async (isRetry = false) => {
    try {
      setLoading(isRetry ? false : true);
      const response = await axios.get(`/api/host/venues/${venueId}/matchmaking-status`);
      setMatchmakingData(response.data.data);
      setLastUpdated(new Date());
      setError(null);
      setRetryCount(0);
    } catch (error) {
      console.error('Error fetching matchmaking data:', error);
      const errorMessage = error.response?.data?.message || 'Failed to load matchmaking data';
      setError(errorMessage);
      
      // Implement exponential backoff for retries
      if (retryCount < 3) {
        const timeout = Math.pow(2, retryCount) * 1000;
        setTimeout(() => {
          setRetryCount(prev => prev + 1);
          fetchMatchmakingData(true);
        }, timeout);
      } else {
        toast.error('Failed to update matchmaking data after multiple attempts');
      }
    } finally {
      setLoading(false);
    }
  }, [venueId, retryCount]);

  // WebSocket setup
  useEffect(() => {
    if (venueId) {
      const echo = new Echo({
        broadcaster: 'pusher',
        key: process.env.MIX_PUSHER_APP_KEY,
        cluster: process.env.MIX_PUSHER_APP_CLUSTER,
        forceTLS: true
      });

      echo.private(`venue.${venueId}`)
        .listen('MatchmakingUpdated', (e) => {
          setMatchmakingData(e.data);
          setLastUpdated(new Date());
        });

      return () => {
        echo.leave(`venue.${venueId}`);
      };
    }
  }, [venueId]);

  // Polling fallback
  useEffect(() => {
    if (venueId) {
      fetchMatchmakingData();
      const interval = setInterval(fetchMatchmakingData, 30000);
      return () => clearInterval(interval);
    }
  }, [venueId, fetchMatchmakingData]);

  // Handle match override with validation
  const handleMatchOverride = async (e) => {
    e.preventDefault();
    
    if (overrideData.player1_id === overrideData.player2_id) {
      toast.error('Cannot match a player with themselves');
      return;
    }

    try {
      await axios.post(`/api/host/matchmaking/override`, {
        venue_id: venueId,
        ...overrideData
      });
      
      toast.success('Match override successful');
      setShowOverrideForm(false);
      setOverrideData({
        player1_id: '',
        player2_id: '',
        reason: ''
      });
      fetchMatchmakingData();
    } catch (error) {
      console.error('Error overriding match:', error);
      toast.error(error.response?.data?.message || 'Failed to override match');
    }
  };

  // Handle match cancellation with confirmation
  const handleMatchCancel = async (matchId) => {
    if (!confirm('Are you sure you want to cancel this match? This action cannot be undone.')) {
      return;
    }

    try {
      await axios.post(`/api/host/matchmaking/${matchId}/cancel`);
      toast.success('Match cancelled successfully');
      fetchMatchmakingData();
    } catch (error) {
      console.error('Error cancelling match:', error);
      toast.error('Failed to cancel match');
    }
  };

  // Handle sort change
  const handleSort = (key) => {
    setSortConfig(prev => ({
      key,
      direction: prev.key === key && prev.direction === 'asc' ? 'desc' : 'asc'
    }));
  };

  const getSkillLevelColor = (mmr) => {
    if (mmr >= 2000) return 'bg-purple-100 text-purple-800'; // Professional
    if (mmr >= 1600) return 'bg-blue-100 text-blue-800';    // Expert
    if (mmr >= 1200) return 'bg-green-100 text-green-800';  // Advanced
    if (mmr >= 800) return 'bg-yellow-100 text-yellow-800'; // Intermediate
    return 'bg-gray-100 text-gray-800';                     // Beginner
  };

  const getSkillLevel = (mmr) => {
    if (mmr >= 2000) return 'Professional';
    if (mmr >= 1600) return 'Expert';
    if (mmr >= 1200) return 'Advanced';
    if (mmr >= 800) return 'Intermediate';
    return 'Beginner';
  };

  if (!venueId) {
    return (
      <div className="text-center text-gray-500 py-8">
        Please select a venue to monitor matchmaking.
      </div>
    );
  }

  return (
    <div className="space-y-6 p-4">
      <div className="flex justify-between items-center">
        <div>
          <h2 className="text-xl font-semibold">Matchmaking Monitor</h2>
          <p className="text-sm text-gray-500">
            Last updated: {formatDistanceToNow(lastUpdated, { addSuffix: true })}
          </p>
        </div>
        <button
          onClick={() => setShowOverrideForm(true)}
          className="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors"
        >
          Override Match
        </button>
      </div>

      {error && (
        <div className="bg-red-50 text-red-500 p-4 rounded-lg flex items-center justify-between">
          <span>{error}</span>
          <button 
            onClick={() => fetchMatchmakingData()} 
            className="text-sm underline hover:no-underline"
          >
            Retry
          </button>
        </div>
      )}

      {showOverrideForm && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-lg p-6 max-w-lg w-full mx-4">
            <h3 className="text-lg font-medium mb-4">Override Match</h3>
            <form onSubmit={handleMatchOverride} className="space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700">
                  Player 1
                </label>
                <select
                  value={overrideData.player1_id}
                  onChange={(e) => setOverrideData(prev => ({ ...prev, player1_id: e.target.value }))}
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                  required
                >
                  <option value="">Select player 1...</option>
                  {sortedWaitingPlayers.map(player => (
                    <option key={player.id} value={player.id}>
                      {player.name} (MMR: {player.mmr} - {getSkillLevel(player.mmr)})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-gray-700">
                  Player 2
                </label>
                <select
                  value={overrideData.player2_id}
                  onChange={(e) => setOverrideData(prev => ({ ...prev, player2_id: e.target.value }))}
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                  required
                >
                  <option value="">Select player 2...</option>
                  {sortedWaitingPlayers
                    .filter(p => p.id !== overrideData.player1_id)
                    .map(player => (
                      <option key={player.id} value={player.id}>
                        {player.name} (MMR: {player.mmr} - {getSkillLevel(player.mmr)})
                      </option>
                    ))}
                </select>
              </div>

              {overrideData.player1_id && overrideData.player2_id && (
                <div className="bg-gray-50 p-4 rounded-lg">
                  <h4 className="text-sm font-medium text-gray-700 mb-2">Match Preview</h4>
                  {matchmakingData.waitingPlayers.filter(p => 
                    p.id === overrideData.player1_id || p.id === overrideData.player2_id
                  ).map(player => (
                    <div key={player.id} className="flex items-center justify-between text-sm">
                      <span>{player.name}</span>
                      <span className={`px-2 py-1 rounded-full ${getSkillLevelColor(player.mmr)}`}>
                        {getSkillLevel(player.mmr)} - MMR: {player.mmr}
                      </span>
                    </div>
                  ))}
                </div>
              )}

              <div>
                <label className="block text-sm font-medium text-gray-700">
                  Reason for Override
                </label>
                <textarea
                  value={overrideData.reason}
                  onChange={(e) => setOverrideData(prev => ({ ...prev, reason: e.target.value }))}
                  className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                  rows={3}
                  required
                  placeholder="Please provide a reason for this match override..."
                />
              </div>

              <div className="flex justify-end gap-4">
                <button
                  type="button"
                  onClick={() => setShowOverrideForm(false)}
                  className="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark transition-colors"
                >
                  Create Match
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {loading && !matchmakingData.waitingPlayers.length ? (
        <div className="flex justify-center py-8">
          <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
        </div>
      ) : (
        <div className="space-y-8">
          {/* Waiting Players */}
          <div>
            <h3 className="text-lg font-medium mb-4">
              Waiting Players ({matchmakingData.waitingPlayers.length})
            </h3>
            <div className="bg-white rounded-lg shadow overflow-hidden">
              <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Player
                    </th>
                    <th 
                      className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                      onClick={() => handleSort('mmr')}
                    >
                      MMR / Skill Level {sortConfig.key === 'mmr' && (
                        sortConfig.direction === 'asc' ? '↑' : '↓'
                      )}
                    </th>
                    <th 
                      className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                      onClick={() => handleSort('waiting_since')}
                    >
                      Waiting Time {sortConfig.key === 'waiting_since' && (
                        sortConfig.direction === 'asc' ? '↑' : '↓'
                      )}
                    </th>
                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {sortedWaitingPlayers.map(player => (
                    <tr key={player.id} className="hover:bg-gray-50">
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="flex items-center">
                          <div className="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center text-sm font-medium text-gray-600">
                            {player.name.charAt(0)}
                          </div>
                          <div className="ml-4">
                            <div className="text-sm font-medium text-gray-900">
                              {player.name}
                            </div>
                          </div>
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className={`px-2 py-1 text-xs font-semibold rounded-full ${getSkillLevelColor(player.mmr)}`}>
                          {getSkillLevel(player.mmr)} - MMR: {player.mmr}
                        </span>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <div className="text-sm text-gray-500">
                          {formatDistanceToNow(new Date(player.waiting_since), { addSuffix: true })}
                        </div>
                      </td>
                      <td className="px-6 py-4 whitespace-nowrap">
                        <span className="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                          Waiting
                        </span>
                      </td>
                    </tr>
                  ))}
                  {matchmakingData.waitingPlayers.length === 0 && (
                    <tr>
                      <td colSpan={4} className="px-6 py-4 text-center text-sm text-gray-500">
                        No players waiting
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default MatchmakingMonitor;