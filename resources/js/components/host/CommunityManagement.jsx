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
        const response = await axios.get('/communities/my-communities');
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

  const handleCreateCommunity = async (values) => {
    try {
      const response = await axios.post('/communities', values);
      if (response.data.status === 'success') {
        setCommunities([...communities, response.data.data.community]);
        setShowCreateModal(false);
      }
    } catch (err) {
      console.error('Error creating community:', err);
      setError('Failed to create community');
    }
  };

  const CommunityCard = ({ community }) => (
    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
      {/* Header */}
      <div className="mb-4">
        <div className="flex items-start justify-between mb-2">
          <h3 className="font-bold text-lg text-gray-900 flex-1">{community.name}</h3>
          {community.has_icon && community.icon_url && (
            <img 
              src={community.icon_url} 
              alt={community.name}
              className="w-12 h-12 rounded-lg object-cover ml-3"
            />
          )}
        </div>
        <p className="text-gray-600 text-sm leading-relaxed mb-3 line-clamp-2">{community.description}</p>
      </div>

      {/* Community Info Grid */}
      <div className="grid grid-cols-2 gap-3 mb-4">
        <div className="flex items-center text-sm text-gray-600">
          <span className="mr-2 text-base">🏸</span>
          <span className="font-medium">{community.sport?.name || 'Sport'}</span>
        </div>
        <div className="flex items-center text-sm text-gray-600">
          <span className="mr-2 text-base">👥</span>
          <span>{community.member_count || 0} member</span>
        </div>
        <div className="flex items-center text-sm text-gray-600">
          <span className="mr-2 text-base">⭐</span>
          <span>Fokus: {community.skill_level_focus || 'Mixed'}</span>
        </div>
        <div className="flex items-center text-sm text-gray-600">
          <span className="mr-2 text-base">🎯</span>
          <span>Rating: {community.average_skill_rating ? parseFloat(community.average_skill_rating).toFixed(1) : 'N/A'}</span>
        </div>
      </div>

      {/* Location */}
      <div className="flex items-center text-sm text-gray-600 mb-4">
        <span className="mr-2 text-base">📍</span>
        <span className="flex-1 truncate">{community.venue_city || community.city}</span>
      </div>

      {/* Schedule */}
      {community.regular_schedule && (
        <div className="flex items-center text-sm text-gray-600 mb-4">
          <span className="mr-2 text-base">⏰</span>
          <span>{community.regular_schedule}</span>
        </div>
      )}

      {/* Price and Type */}
      <div className="flex items-center justify-between mb-4">
        <div className="text-lg font-bold text-primary">
          {community.membership_fee ? `Rp ${community.membership_fee.toLocaleString()}/bulan` : 'GRATIS'}
        </div>
        <span className={`px-3 py-1 rounded-full text-sm font-medium ${
          community.community_type === 'public' ? 'bg-green-100 text-green-700' :
          community.community_type === 'private' ? 'bg-blue-100 text-blue-700' :
          'bg-orange-100 text-orange-700'
        }`}>
          {community.community_type === 'public' ? 'Publik' :
           community.community_type === 'private' ? 'Privat' : 'Undangan'}
        </span>
      </div>

      {/* Action Buttons */}
      <div className="flex space-x-2">
        <button
          onClick={() => {
            setSelectedCommunity(community);
            setShowEditModal(true);
          }}
          className="flex-1 bg-primary text-white py-2 px-4 rounded-lg font-medium hover:bg-primary-dark transition-colors text-sm"
        >
          Edit
        </button>
        <button
          onClick={() => handleDeleteCommunity(community.id)}
          className="flex-1 bg-red-500 text-white py-2 px-4 rounded-lg font-medium hover:bg-red-600 transition-colors text-sm"
        >
          Hapus
        </button>
      </div>
    </div>
  );

  const StatsCard = ({ icon, label, value, color = 'primary' }) => (
    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
      <div className="flex items-center space-x-3">
        <div className={`p-2 bg-${color}/10 rounded-lg`}>
          <span className="text-lg">{icon}</span>
        </div>
        <div>
          <p className="text-sm text-gray-600">{label}</p>
          <p className="text-xl font-semibold text-gray-900">{value}</p>
        </div>
      </div>
    </div>
  );

  if (loading) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  return (
    <div className="p-4 space-y-4">
      {/* Error Message */}
      {error && (
        <div className="bg-red-50 border border-red-200 rounded-xl p-4">
          <p className="text-red-600 text-sm">{error}</p>
        </div>
      )}

      {/* Welcome Message for New Hosts */}
      {communities.length === 0 && !loading && (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <div className="text-center">
            <div className="text-6xl mb-4">🏟️</div>
            <h2 className="text-xl font-bold text-gray-900 mb-2">Selamat Datang di SportPWA Host!</h2>
            <p className="text-gray-600 mb-4">
              Untuk memulai, buat komunitas pertama Anda. Ini akan memungkinkan Anda untuk:
            </p>
            <ul className="text-sm text-gray-600 text-left space-y-2 mb-6">
              <li>• Mengelola event dan turnamen</li>
              <li>• Membangun komunitas olahraga</li>
              <li>• Mengatur jadwal dan venue</li>
              <li>• Mengelola sistem matchmaking</li>
            </ul>
            <button
              onClick={() => setShowCreateModal(true)}
              className="bg-primary text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-dark transition-colors"
            >
              Buat Komunitas Pertama
            </button>
          </div>
        </div>
      )}

      {/* Stats Cards */}
      {communities.length > 0 && (
        <div className="grid grid-cols-3 gap-3">
          <StatsCard 
            icon="👥" 
            label="Total Member" 
            value={stats.total_members}
          />
          <StatsCard 
            icon="📅" 
            label="Event Aktif" 
            value={stats.active_events}
          />
          <StatsCard 
            icon="⭐" 
            label="Rating Rata-rata" 
            value={stats.average_rating ? stats.average_rating.toFixed(1) : 'N/A'}
          />
        </div>
      )}

      {/* Communities List */}
      {communities.length > 0 && (
        <div>
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-lg font-semibold text-gray-900">Komunitas Anda</h2>
            <button
              onClick={() => setShowCreateModal(true)}
              className="bg-primary text-white px-4 py-2 rounded-lg font-medium hover:bg-primary-dark transition-colors text-sm"
            >
              Tambah Komunitas
            </button>
          </div>
          
          <div className="space-y-4">
            {communities.map((community) => (
              <CommunityCard key={community.id} community={community} />
            ))}
          </div>
        </div>
      )}

      {/* Modals */}
      {showEditModal && selectedCommunity && (
        <CommunityEditModal
          community={selectedCommunity}
          onClose={() => setShowEditModal(false)}
          onUpdate={handleUpdateCommunity}
        />
      )}
    </div>
  );
};

export default CommunityManagement;