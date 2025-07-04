import React from 'react';
import axios from 'axios';
import CommunityForm from '../common/CommunityForm';

const CommunityEditModal = ({ community, onClose, onUpdate }) => {
  const [loading, setLoading] = React.useState(false);
  const [error, setError] = React.useState(null);

  const handleSubmit = async (values) => {
    setLoading(true);
    setError(null);

    try {
      const response = await axios.put(`/api/communities/${community.id}`, values);
      
      if (response.data.status === 'success') {
        onUpdate(response.data.data.community);
        onClose();
      }
    } catch (err) {
      console.error('Error updating community:', err);
      setError(err.response?.data?.message || 'Failed to update community');
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
        <div className="sticky top-0 bg-white border-b px-6 py-4 flex items-center justify-between">
          <h2 className="text-xl font-semibold text-gray-800">Edit Community</h2>
          <button
            onClick={onClose}
            className="text-gray-500 hover:text-gray-700"
          >
            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <div className="p-6">
          <CommunityForm
            initialValues={{
              name: community.name,
              description: community.description,
              community_type: community.community_type,
              skill_level_focus: community.skill_level_focus,
              venue_name: community.venue_name,
              venue_address: community.venue_address,
              venue_city: community.venue_city,
              latitude: community.latitude,
              longitude: community.longitude,
              regular_schedule: community.regular_schedule || '',
              membership_fee: community.membership_fee || 0,
              max_members: community.max_members || 50,
              is_premium_required: community.is_premium_required || false
            }}
            onSubmit={handleSubmit}
            loading={loading}
            error={error}
            submitButtonText="Save Changes"
          />
        </div>
      </div>
    </div>
  );
};

export default CommunityEditModal; 