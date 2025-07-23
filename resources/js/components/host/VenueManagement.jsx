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
      const response = await axios.get('/host/venues');
      setVenues(Array.isArray(response.data) ? response.data : 
               Array.isArray(response.data.data) ? response.data.data : []);
      setError(null);
    } catch (error) {
      console.error('Error fetching venues:', error);
      setError('Failed to load venues');
      setVenues([]);
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
        await axios.put(`/host/venues/${selectedVenue.id}`, values);
      } else {
        await axios.post('/host/venues', values);
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
    if (!confirm('Apakah Anda yakin ingin menghapus venue ini?')) {
      return;
    }

    try {
      setLoading(true);
      await axios.delete(`/host/venues/${venueId}`);
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
      return 'Tidak ditentukan';
    }
    
    const today = new Date().toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
    const todayHours = operatingHours[today];
    
    if (!todayHours) {
      return 'Tutup hari ini';
    }
    
    return `${todayHours.open || '00:00'} - ${todayHours.close || '00:00'}`;
  };

  const VenueCard = ({ venue }) => (
    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-4">
      {/* Header */}
      <div className="mb-4">
        <div className="flex justify-between items-start mb-2">
          <div className="flex-1">
            <h3 className="font-bold text-lg text-gray-900">{venue.name}</h3>
            <p className="text-sm text-gray-600 mt-1">{venue.address}</p>
          </div>
          <span className={`px-3 py-1 rounded-full text-sm font-medium ${
            venue.status === 'active' ? 'bg-green-100 text-green-700' :
            venue.status === 'inactive' ? 'bg-red-100 text-red-700' :
            'bg-yellow-100 text-yellow-700'
          }`}>
            {venue.status === 'active' ? 'Aktif' : 
             venue.status === 'inactive' ? 'Tidak Aktif' : 'Tidak Diketahui'}
          </span>
        </div>
      </div>

      {/* Venue Info Grid */}
      <div className="grid grid-cols-2 gap-3 mb-4">
        <div className="flex items-center text-sm text-gray-600">
          <span className="mr-2 text-base">🏸</span>
          <span>{venue.courts_count || 0} Courts</span>
        </div>
        <div className="flex items-center text-sm text-gray-600">
          <span className="mr-2 text-base">👥</span>
          <span>Kapasitas: {venue.capacity || 'N/A'}</span>
        </div>
        <div className="flex items-center text-sm text-gray-600">
          <span className="mr-2 text-base">⏰</span>
          <span>{formatOperatingHours(venue.operating_hours)}</span>
        </div>
        <div className="flex items-center text-sm text-gray-600">
          <span className="mr-2 text-base">📅</span>
          <span>{venue.upcoming_events || 0} Event</span>
        </div>
      </div>

      {/* Amenities */}
      {venue.amenities && Array.isArray(venue.amenities) && venue.amenities.length > 0 && (
        <div className="mb-4">
          <h4 className="text-sm font-medium text-gray-700 mb-2">Fasilitas</h4>
          <div className="flex flex-wrap gap-2">
            {venue.amenities.map((amenity, index) => (
              <span
                key={index}
                className="px-3 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary"
              >
                {amenity}
              </span>
            ))}
          </div>
        </div>
      )}

      {/* Recent Events */}
      {venue.events && venue.events.length > 0 && (
        <div className="mb-4">
          <h4 className="text-sm font-medium text-gray-700 mb-2">Event Terbaru</h4>
          <div className="space-y-1">
            {venue.events.slice(0, 3).map(event => (
              <div key={event.id} className="text-xs text-gray-600 bg-gray-50 p-2 rounded">
                {event.title} - {event.event_date} at {event.start_time}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Action Buttons */}
      <div className="flex space-x-2">
        <button
          onClick={() => {
            setSelectedVenue(venue);
            setShowAddForm(true);
          }}
          className="flex-1 bg-primary text-white py-2 px-4 rounded-lg font-medium hover:bg-primary-dark transition-colors text-sm"
        >
          Edit
        </button>
        <button
          onClick={() => handleDelete(venue.id)}
          className="flex-1 bg-red-500 text-white py-2 px-4 rounded-lg font-medium hover:bg-red-600 transition-colors text-sm"
        >
          Hapus
        </button>
      </div>
    </div>
  );

  if (loading && venues.length === 0) {
    return (
      <div className="flex justify-center items-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  return (
    <div className="p-4 space-y-4">
      {/* Header */}
      <div className="flex justify-between items-center">
        <h1 className="text-lg font-semibold text-gray-900">Manajemen Venue</h1>
        {!showAddForm && (
          <button
            onClick={() => setShowAddForm(true)}
            className="bg-primary text-white px-4 py-2 rounded-lg font-medium hover:bg-primary-dark transition-colors text-sm"
          >
            Tambah Venue
          </button>
        )}
      </div>

      {/* Error Message */}
      {error && (
        <div className="bg-red-50 border border-red-200 rounded-xl p-4">
          <p className="text-red-600 text-sm">{error}</p>
        </div>
      )}

      {/* Form or List */}
      {showAddForm ? (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
          <div className="mb-4">
            <h2 className="text-lg font-semibold text-gray-900">
              {selectedVenue ? 'Edit Venue' : 'Tambah Venue Baru'}
            </h2>
          </div>
          <VenueForm
            initialValues={selectedVenue || {}}
            onSubmit={handleSubmit}
            submitButtonText={selectedVenue ? 'Update Venue' : 'Tambah Venue'}
            onCancel={() => {
              setShowAddForm(false);
              setSelectedVenue(null);
            }}
          />
        </div>
      ) : (
        <>
          {venues.length === 0 ? (
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
              <div className="text-center py-8">
                <div className="text-6xl mb-4">🏟️</div>
                <h3 className="font-semibold text-gray-900 mb-2">Belum ada venue</h3>
                <p className="text-gray-600 text-sm mb-4">Mulai dengan membuat venue pertama Anda</p>
                <button
                  onClick={() => setShowAddForm(true)}
                  className="bg-primary text-white px-4 py-2 rounded-lg font-medium hover:bg-primary-dark transition-colors"
                >
                  Tambah Venue
                </button>
              </div>
            </div>
          ) : (
            <div className="space-y-4">
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