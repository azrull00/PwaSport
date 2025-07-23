import React from 'react';
import { formatTime, formatDate } from '../../utils/dateUtils';

const StatCard = ({ title, value, subtext, icon, trend = null }) => (
  <div className="bg-white rounded-lg shadow p-6">
    <div className="flex items-start justify-between">
      <div>
        <p className="text-sm font-medium text-gray-600">{title}</p>
        <p className="mt-2 text-3xl font-semibold text-gray-900">{value}</p>
        {subtext && <p className="mt-1 text-sm text-gray-500">{subtext}</p>}
        {trend && (
          <div className={`mt-2 flex items-center text-sm ${
            trend.type === 'increase' ? 'text-green-600' : 'text-red-600'
          }`}>
            {trend.type === 'increase' ? (
              <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
              </svg>
            ) : (
              <svg className="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 17h8m0 0v-8m0 8l-8-8-4 4-6-6" />
              </svg>
            )}
            <span>{trend.value}% from last period</span>
          </div>
        )}
      </div>
      {icon && (
        <div className="p-3 bg-primary/10 rounded-full">
          {icon}
        </div>
      )}
    </div>
  </div>
);

const DashboardStats = ({ venueId }) => {
  const [stats, setStats] = React.useState({
    activeMatches: 0,
    waitingPlayers: 0,
    totalPlayersToday: 0,
    averageWaitTime: 0,
    courtUtilization: 0,
    upcomingMatches: [],
    recentMatches: [],
    trends: {
      players: { type: 'increase', value: 12 },
      waitTime: { type: 'decrease', value: 8 },
      utilization: { type: 'increase', value: 5 }
    }
  });
  const [loading, setLoading] = React.useState(true);
  const [error, setError] = React.useState(null);

  React.useEffect(() => {
    const fetchStats = async () => {
      if (!venueId) return;

      try {
        setLoading(true);
        const response = await axios.get(`/host/venues/${venueId}/stats`);
        setStats(response.data.data);
        setError(null);
      } catch (err) {
        console.error('Error fetching stats:', err);
        setError('Failed to load dashboard statistics');
      } finally {
        setLoading(false);
      }
    };

    fetchStats();
    // Set up real-time updates
    const channel = window.Echo.private(`venue.${venueId}`);
    channel.listen('StatsUpdated', (e) => {
      setStats(prevStats => ({
        ...prevStats,
        ...e.stats
      }));
    });

    return () => {
      channel.stopListening('StatsUpdated');
    };
  }, [venueId]);

  if (loading) {
    return (
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 animate-pulse">
        {[...Array(4)].map((_, i) => (
          <div key={i} className="bg-white rounded-lg shadow p-6 h-32">
            <div className="h-4 bg-gray-200 rounded w-1/2 mb-4"></div>
            <div className="h-8 bg-gray-200 rounded w-1/3"></div>
          </div>
        ))}
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-red-50 text-red-500 p-4 rounded-lg">
        {error}
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Key Stats */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatCard
          title="Active Matches"
          value={stats.activeMatches}
          subtext="Currently in progress"
          icon={
            <svg className="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
          }
        />
        <StatCard
          title="Waiting Players"
          value={stats.waitingPlayers}
          subtext="In queue"
          trend={stats.trends.players}
          icon={
            <svg className="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
          }
        />
        <StatCard
          title="Average Wait Time"
          value={`${Math.round(stats.averageWaitTime)} min`}
          trend={stats.trends.waitTime}
          icon={
            <svg className="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          }
        />
        <StatCard
          title="Court Utilization"
          value={`${stats.courtUtilization}%`}
          trend={stats.trends.utilization}
          icon={
            <svg className="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
            </svg>
          }
        />
      </div>

      {/* Upcoming and Recent Matches */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {/* Upcoming Matches */}
        <div className="bg-white rounded-lg shadow">
          <div className="p-6">
            <h3 className="text-lg font-medium text-gray-900">Upcoming Matches</h3>
            <div className="mt-4 space-y-4">
              {stats.upcomingMatches.map(match => (
                <div key={match.id} className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-gray-900">
                      {match.player1} vs {match.player2}
                    </p>
                    <p className="text-sm text-gray-500">Court {match.court_number}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-sm font-medium text-gray-900">
                      {formatTime(match.scheduled_time)}
                    </p>
                    <p className="text-sm text-gray-500">
                      {formatDate(match.scheduled_date)}
                    </p>
                  </div>
                </div>
              ))}
              {stats.upcomingMatches.length === 0 && (
                <p className="text-gray-500 text-center py-4">No upcoming matches</p>
              )}
            </div>
          </div>
        </div>

        {/* Recent Matches */}
        <div className="bg-white rounded-lg shadow">
          <div className="p-6">
            <h3 className="text-lg font-medium text-gray-900">Recent Matches</h3>
            <div className="mt-4 space-y-4">
              {stats.recentMatches.map(match => (
                <div key={match.id} className="flex items-center justify-between">
                  <div>
                    <p className="font-medium text-gray-900">
                      {match.player1} vs {match.player2}
                    </p>
                    <p className="text-sm text-gray-500">Court {match.court_number}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-sm font-medium text-gray-900">
                      {match.winner ? `${match.winner} won` : 'Draw'}
                    </p>
                    <p className="text-sm text-gray-500">
                      {formatTime(match.end_time)}
                    </p>
                  </div>
                </div>
              ))}
              {stats.recentMatches.length === 0 && (
                <p className="text-gray-500 text-center py-4">No recent matches</p>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default DashboardStats;