import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { useNavigate } from 'react-router-dom';
import CommunityForm from '../common/CommunityForm';

const CommunityCreation = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [sports, setSports] = useState([]);
  const navigate = useNavigate();

  useEffect(() => {
    const fetchSports = async () => {
      try {
        const response = await axios.get('/api/sports');
        setSports(response.data.data.sports);
      } catch (err) {
        console.error('Error fetching sports:', err);
        setError('Failed to load sports list');
      }
    };
    fetchSports();
  }, []);

  const handleSubmit = async (values) => {
    setLoading(true);
    setError(null);

    try {
      const response = await axios.post('/api/communities', values);
      
      if (response.data.status === 'success') {
        navigate('/host/communities');
      }
    } catch (err) {
      console.error('Error creating community:', err);
      setError(err.response?.data?.message || 'Failed to create community');
      setLoading(false);
    }
  };

  return (
    <div className="container mx-auto px-4 py-8">
      <h1 className="text-2xl font-bold text-gray-800 mb-6">Create New Community</h1>

      <CommunityForm
        onSubmit={handleSubmit}
        loading={loading}
        error={error}
        sports={sports}
        submitButtonText="Create Community"
      />
    </div>
  );
};

export default CommunityCreation; 