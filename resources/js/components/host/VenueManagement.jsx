import React, { useState, useEffect } from 'react';
import axios from 'axios';
import VenueForm from '../common/VenueForm';

const VenueManagement = () => {
  const [venues, setVenues] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [selectedVenue, setSelectedVenue] = useState(null);

  // Fetch venues
  const fetchVenues = async () => {
    try {
      setLoading(true);
      const response = await axios.get('/api/host/venues');
      setVenues(response.data.data || []);
      setError(null);
    } catch (error) {
      console.error('Error fetching venues:', error);
      setError('Failed to load venues');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchVenues();
  }, []);

  // Handle form submission
  const handleSubmit = async (values) => {
    try {
      setLoading(true);
      if (selectedVenue) {
        await axios.put(`/api/host/venues/${selectedVenue.id}`, values);
      } else {
        await axios.post('/api/host/venues', values);
      }
      
      setShowAddForm(false);
      setSelectedVenue(null);
      fetchVenues();
    } catch (error) {
      console.error('Error saving venue:', error);
      throw error.response?.data?.message || 'Failed to save venue';
    }
  };

  // Handle venue deletion
  const handleDelete = async (venueId) => {
    if (!confirm('Are you sure you want to delete this venue?')) {
      return;
    }

    try {
      setLoading(true);
      await axios.delete(`/api/host/venues/${venueId}`);
      fetchVenues();
    } catch (error) {
      console.error('Error deleting venue:', error);
      setError('Failed to delete venue');
    } finally {
      setLoading(false);
    }
  };

  const formatOperatingHours = (operatingHours) => {
    if (!operatingHours || typeof operatingHours !== 'object') {
      return 'Not specified';
    }
    
    const today = new Date().toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
    const todayHours = operatingHours[today];
    
    if (!todayHours) {
      return 'Closed today';
    }
    
    return `${todayHours.open || '00:00'} - ${todayHours.close || '00:00'}`;
  };

  const VenueCard = ({ venue }) => (
    <div className="bg-white rounded-lg shadow-md overflow-hidden">
      <div className="p-6">
        <div className="flex justify-between items-start">
          <div>
            <h3 className="text-xl font-semibold text-gray-900">{venue.name}</h3>
            <p className="text-sm text-gray-500 mt-1">{venue.address}</p>
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium mt-2 ${
              venue.status === 'active' ? 'bg-green-100 text-green-800' :
              venue.status === 'inactive' ? 'bg-red-100 text-red-800' :
              'bg-yellow-100 text-yellow-800'
            }`}>
              {venue.status || 'Unknown'}
            </span>
          </div>
          <div className="flex space-x-2">
            <button
              onClick={() => {
                setSelectedVenue(venue);
                setShowAddForm(true);
              }}
              className="p-2 text-primary hover:bg-primary/10 rounded-full"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
              </svg>
            </button>
            <button
              onClick={() => handleDelete(venue.id)}
              className="p-2 text-red-500 hover:bg-red-50 rounded-full"
            >
              <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
              </svg>
            </button>
          </div>
        </div>

        <div className="mt-4 grid grid-cols-2 gap-4">
          <div>
            <h4 className="text-sm font-medium text-gray-700">Courts</h4>
            <p className="mt-1 text-sm text-gray-900">{venue.courts_count || 'N/A'}</p>
          </div>
          <div>
            <h4 className="text-sm font-medium text-gray-700">Capacity</h4>
            <p className="mt-1 text-sm text-gray-900">{venue.capacity || 'N/A'}</p>
          </div>
          <div>
            <h4 className="text-sm font-medium text-gray-700">Today's Hours</h4>
            <p className="mt-1 text-sm text-gray-900">
              {formatOperatingHours(venue.operating_hours)}
            </p>
          </div>
          <div>
            <h4 className="text-sm font-medium text-gray-700">Upcoming Events</h4>
            <p className="mt-1 text-sm text-gray-900">{venue.upcoming_events || 0}</p>
          </div>
        </div>

        {venue.amenities && Array.isArray(venue.amenities) && venue.amenities.length > 0 && (
          <div className="mt-4">
            <h4 className="text-sm font-medium text-gray-700">Amenities</h4>
            <div className="mt-2 flex flex-wrap gap-2">
              {venue.amenities.map((amenity, index) => (
                <span
                  key={index}
                  className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-primary/10 text-primary"
                >
                  {amenity}
                </span>
              ))}
            </div>
          </div>
        )}

        {venue.events && venue.events.length > 0 && (
          <div className="mt-4">
            <h4 className="text-sm font-medium text-gray-700">Recent Events</h4>
            <div className="mt-2 space-y-1">
              {venue.events.slice(0, 3).map(event => (
                <div key={event.id} className="text-xs text-gray-600">
                  {event.title} - {event.event_date} at {event.start_time}
                </div>
              ))}
            </div>
          </div>
        )}
      </div>
    </div>
  );

  if (loading && venues.length === 0) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-primary"></div>
      </div>
    );
  }

  return (
    <div className="space-y-6 p-4">
      <div className="flex justify-between items-center">
        <h1 className="text-2xl font-bold">Venue Management</h1>
        {!showAddForm && (
          <button
            onClick={() => setShowAddForm(true)}
            className="px-4 py-2 bg-primary text-white rounded-lg hover:bg-primary-dark"
          >
            Add New Venue
          </button>
        )}
      </div>

      {error && (
        <div className="bg-red-50 text-red-500 p-4 rounded-lg">
          {error}
        </div>
      )}

      {showAddForm ? (
        <div className="bg-white rounded-lg shadow p-6">
          <VenueForm
            initialValues={selectedVenue || {}}
            onSubmit={handleSubmit}
            submitButtonText={selectedVenue ? 'Update Venue' : 'Add Venue'}
            onCancel={() => {
              setShowAddForm(false);
              setSelectedVenue(null);
            }}
          />
        </div>
      ) : (
        <>
          {venues.length === 0 ? (
            <div className="text-center py-12">
              <h3 className="mt-2 text-sm font-medium text-gray-900">No venues</h3>
              <p className="mt-1 text-sm text-gray-500">Get started by creating a new venue.</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {venues.map(venue => (
                <VenueCard key={venue.id} venue={venue} />
              ))}
            </div>
          )}
        </>
      )}
    </div>
  );
};

export default VenueManagement; 