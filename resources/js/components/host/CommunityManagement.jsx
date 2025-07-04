import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { Link } from 'react-router-dom';
import LocationPicker from '../common/LocationPicker';
import DistanceDisplay from '../common/DistanceDisplay';
import CommunityEditModal from './CommunityEditModal';

const CommunityManagement = ({ userToken, onNavigate, showCreate = false }) => {
  const [communities, setCommunities] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [selectedCommunity, setSelectedCommunity] = useState(null);
  const [showEditModal, setShowEditModal] = useState(false);
  const [showCreateModal, setShowCreateModal] = useState(showCreate);
  const [stats, setStats] = useState({
    total_members: 0,
    active_events: 0,
    average_rating: 0
  });

  // Fetch host's communities
  useEffect(() => {
    const fetchCommunities = async () => {
      try {
        setLoading(true);
        const response = await axios.get('/api/communities/my-communities');
        setCommunities(response.data.data.communities);
        
        // Calculate stats
        const totalMembers = response.data.data.communities.reduce(
          (sum, community) => sum + community.member_count, 0
        );
        const avgRating = response.data.data.communities.reduce(
          (sum, community) => sum + community.average_skill_rating, 0
        ) / response.data.data.communities.length;

        setStats({
          total_members: totalMembers,
          active_events: response.data.data.active_events || 0,
          average_rating: avgRating
        });

        // Show create modal if no communities exist and showCreate is true
        if (response.data.data.communities.length === 0 && showCreate) {
          setShowCreateModal(true);
        }
      } catch (err) {
        console.error('Error fetching communities:', err);
        setError('Failed to load communities');
      } finally {
        setLoading(false);
      }
    };

    fetchCommunities();
  }, [showCreate]);

  const handleDeleteCommunity = async (communityId) => {
    if (!window.confirm('Are you sure you want to delete this community?')) {
      return;
    }

    try {
      await axios.delete(`/api/communities/${communityId}`);
      setCommunities(communities.filter(c => c.id !== communityId));
    } catch (err) {
      console.error('Error deleting community:', err);
      setError('Failed to delete community');
    }
  };

  const handleUpdateCommunity = (updatedCommunity) => {
    setCommunities(communities.map(c => 
      c.id === updatedCommunity.id ? updatedCommunity : c
    ));
  };

  const CommunityCard = ({ community }) => (
    <div className="bg-white rounded-lg shadow-md overflow-hidden">
      <div className="relative h-40 bg-gray-200">
        {community.icon_url ? (
          <img
            src={community.icon_url}
            alt={community.name}
            className="w-full h-full object-cover"
          />
        ) : (
          <div className="w-full h-full flex items-center justify-center bg-primary/10">
            <span className="text-4xl text-primary/30">
              {community.name.charAt(0).toUpperCase()}
            </span>
          </div>
        )}
        <div className="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent p-4">
          <h3 className="text-white font-semibold text-xl">{community.name}</h3>
          <p className="text-white/80 text-sm">{community.sport?.name}</p>
        </div>
      </div>

      <div className="p-4">
        <div className="flex items-center justify-between mb-4">
          <div className="flex items-center space-x-2">
            <span className="px-2 py-1 bg-primary/10 text-primary rounded-full text-sm">
              {community.community_type}
            </span>
            <span className="px-2 py-1 bg-secondary/10 text-secondary rounded-full text-sm">
              {community.skill_level_focus}
            </span>
          </div>
          <div className="text-sm text-gray-600">
            {community.member_count} members
          </div>
        </div>

        <div className="space-y-2 mb-4">
          <div className="flex items-start space-x-2">
            <svg className="w-5 h-5 text-gray-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <div className="flex-1">
              <p className="text-sm text-gray-600">{community.venue_name}</p>
              <p className="text-xs text-gray-500">{community.venue_address}</p>
            </div>
          </div>

          {community.regular_schedule && (
            <div className="flex items-start space-x-2">
              <svg className="w-5 h-5 text-gray-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <p className="text-sm text-gray-600">{community.regular_schedule}</p>
            </div>
          )}
        </div>

        <div className="flex items-center justify-between pt-4 border-t">
          <div className="flex space-x-2">
            <button
              onClick={() => {
                setSelectedCommunity(community);
                setShowEditModal(true);
              }}
              className="px-3 py-1.5 text-sm bg-primary text-white rounded-md hover:bg-primary-dark transition-colors"
            >
              Edit
            </button>
            <button
              onClick={() => handleDeleteCommunity(community.id)}
              className="px-3 py-1.5 text-sm bg-red-500 text-white rounded-md hover:bg-red-600 transition-colors"
            >
              Delete
            </button>
          </div>
          <Link
            to={`/host/communities/${community.id}/manage`}
            className="px-3 py-1.5 text-sm bg-secondary text-white rounded-md hover:bg-secondary-dark transition-colors"
          >
            Manage
          </Link>
        </div>
      </div>
    </div>
  );

  const StatsCard = ({ icon, label, value }) => (
    <div className="bg-white rounded-lg shadow-md p-4">
      <div className="flex items-center space-x-3">
        <div className="p-2 bg-primary/10 rounded-lg">
          {icon}
        </div>
        <div>
          <p className="text-sm text-gray-600">{label}</p>
          <p className="text-2xl font-semibold text-gray-800">{value}</p>
        </div>
      </div>
    </div>
  );

  return (
    <div className="container mx-auto px-4 py-8">
      {/* Welcome Message for New Hosts */}
      {communities.length === 0 && !loading && (
        <div className="bg-white rounded-lg shadow-md p-6 mb-8">
          <h2 className="text-2xl font-bold text-gray-800 mb-4">Welcome to SportPWA Host!</h2>
          <p className="text-gray-600 mb-4">
            To get started, create your first community. This will allow you to:
          </p>
          <ul className="list-disc list-inside text-gray-600 mb-6">
            <li>Organize and manage sports events</li>
            <li>Build a community of players</li>
            <li>Set up venues and courts</li>
            <li>Handle matchmaking and player management</li>
          </ul>
          <button
            onClick={() => setShowCreateModal(true)}
            className="px-6 py-3 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors"
          >
            Create Your First Community
          </button>
        </div>
      )}

      <div className="flex items-center justify-between mb-8">
        <h1 className="text-2xl font-bold text-gray-800">Community Management</h1>
        {communities.length > 0 && (
          <button
            onClick={() => setShowCreateModal(true)}
            className="px-4 py-2 bg-primary text-white rounded-md hover:bg-primary-dark transition-colors"
          >
            Create Community
          </button>
        )}
      </div>

      {error && (
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
          {error}
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <StatsCard
          icon={
            <svg className="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          }
          label="Total Members"
          value={stats.total_members}
        />
        <StatsCard
          icon={
            <svg className="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          }
          label="Active Events"
          value={stats.active_events}
        />
        <StatsCard
          icon={
            <svg className="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
            </svg>
          }
          label="Average Rating"
          value={stats.average_rating.toFixed(1)}
        />
      </div>

      {loading ? (
        <div className="flex justify-center items-center h-64">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
        </div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {communities.map(community => (
            <CommunityCard key={community.id} community={community} />
          ))}
        </div>
      )}

      {/* Create/Edit Modal */}
      {(showCreateModal || showEditModal) && (
        <CommunityEditModal
          community={selectedCommunity}
          onClose={() => {
            setShowCreateModal(false);
            setShowEditModal(false);
            setSelectedCommunity(null);
          }}
          onSave={(community) => {
            if (selectedCommunity) {
              handleUpdateCommunity(community);
            } else {
              setCommunities([...communities, community]);
            }
            setShowCreateModal(false);
            setShowEditModal(false);
            setSelectedCommunity(null);
          }}
          isNew={!selectedCommunity}
        />
      )}
    </div>
  );
};

export default CommunityManagement; 