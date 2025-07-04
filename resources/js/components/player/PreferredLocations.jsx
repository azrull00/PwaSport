import React, { useState, useEffect } from 'react';
import axios from 'axios';
import LocationPicker from '../common/LocationPicker';
import DistanceDisplay from '../common/DistanceDisplay';

const PreferredLocations = () => {
  const [locations, setLocations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [showAddForm, setShowAddForm] = useState(false);
  const [currentLocation, setCurrentLocation] = useState(null);
  const [maxLocations, setMaxLocations] = useState(3);
  const [formData, setFormData] = useState({
    area_name: '',
    radius_km: 5,
    address: '',
    city: '',
    district: '',
    province: '',
    country: 'Indonesia'
  });

  // Fetch user's preferred locations
  const fetchLocations = async () => {
    try {
      setLoading(true);
      const response = await axios.get('/api/location/preferred-areas');
      setLocations(response.data.data.preferred_areas);
      setMaxLocations(response.data.data.max_areas_allowed);
      setLoading(false);
    } catch (err) {
      console.error('Error fetching locations:', err);
      setError('Failed to load your preferred locations');
      setLoading(false);
    }
  };

  // Get current location on mount
  useEffect(() => {
    if ('geolocation' in navigator) {
      navigator.geolocation.getCurrentPosition(
        (position) => {
          setCurrentLocation({
            lat: position.coords.latitude,
            lng: position.coords.longitude
          });
        },
        (error) => {
          console.error('Error getting location:', error);
        }
      );
    }

    fetchLocations();
  }, []);

  const handleSubmit = async (e) => {
    e.preventDefault();
    try {
      const response = await axios.post('/api/location/preferred-areas', {
        ...formData,
        center_latitude: formData.location?.lat,
        center_longitude: formData.location?.lng
      });

      if (response.data.status === 'success') {
        setLocations([...locations, response.data.data]);
        setShowAddForm(false);
        setFormData({
          area_name: '',
          radius_km: 5,
          address: '',
          city: '',
          district: '',
          province: '',
          country: 'Indonesia',
          location: null
        });
      }
    } catch (err) {
      console.error('Error adding location:', err);
      setError(err.response?.data?.message || 'Failed to add location');
    }
  };

  const handleDelete = async (locationId) => {
    try {
      await axios.delete(`/api/location/preferred-areas/${locationId}`);
      setLocations(locations.filter(loc => loc.id !== locationId));
    } catch (err) {
      console.error('Error deleting location:', err);
      setError('Failed to delete location');
    }
  };

  const handleLocationChange = (location) => {
    setFormData(prev => ({
      ...prev,
      location
    }));
  };

  const handleRadiusChange = (radiusInMeters) => {
    setFormData(prev => ({
      ...prev,
      radius_km: radiusInMeters / 1000
    }));
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-screen">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
      </div>
    );
  }

  return (
    <div className="container mx-auto px-4 py-8">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold text-gray-800">My Preferred Locations</h1>
        {locations.length < maxLocations && (
          <button
            onClick={() => setShowAddForm(true)}
            className="px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark transition-colors"
          >
            Add Location
          </button>
        )}
      </div>

      {error && (
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
          {error}
        </div>
      )}

      {showAddForm && (
        <div className="bg-white rounded-lg shadow-md p-6 mb-6">
          <h2 className="text-xl font-semibold mb-4">Add New Location</h2>
          <form onSubmit={handleSubmit}>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Location Name
                </label>
                <input
                  type="text"
                  value={formData.area_name}
                  onChange={(e) => setFormData(prev => ({ ...prev, area_name: e.target.value }))}
                  className="w-full px-3 py-2 border rounded-md"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">
                  Address
                </label>
                <input
                  type="text"
                  value={formData.address}
                  onChange={(e) => setFormData(prev => ({ ...prev, address: e.target.value }))}
                  className="w-full px-3 py-2 border rounded-md"
                />
              </div>
            </div>

            <div className="mb-4">
              <LocationPicker
                onLocationChange={handleLocationChange}
                onRadiusChange={handleRadiusChange}
                radius={formData.radius_km * 1000}
                height="300px"
              />
            </div>

            <div className="flex justify-end gap-4">
              <button
                type="button"
                onClick={() => setShowAddForm(false)}
                className="px-4 py-2 text-gray-600 hover:text-gray-800"
              >
                Cancel
              </button>
              <button
                type="submit"
                className="px-4 py-2 bg-primary text-white rounded hover:bg-primary-dark"
              >
                Save Location
              </button>
            </div>
          </form>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {locations.map(location => (
          <div key={location.id} className="bg-white rounded-lg shadow-md overflow-hidden">
            <div className="p-4">
              <div className="flex justify-between items-start mb-2">
                <h3 className="text-lg font-semibold text-gray-800">
                  {location.area_name}
                </h3>
                <button
                  onClick={() => handleDelete(location.id)}
                  className="text-red-500 hover:text-red-700"
                >
                  <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
              
              <p className="text-gray-600 text-sm mb-2">{location.address}</p>
              
              {currentLocation && (
                <div className="text-sm text-gray-500">
                  Distance: 
                  <DistanceDisplay
                    currentLocation={currentLocation}
                    targetLocation={{
                      lat: location.center_latitude,
                      lng: location.center_longitude
                    }}
                    className="ml-1"
                  />
                </div>
              )}
              
              <div className="text-sm text-gray-500">
                Radius: {location.radius_km}km
              </div>
            </div>
            
            <div className="h-[200px] relative">
              <LocationPicker
                initialLocation={{
                  lat: location.center_latitude,
                  lng: location.center_longitude
                }}
                radius={location.radius_km * 1000}
                height="200px"
                readOnly={true}
              />
            </div>
          </div>
        ))}
      </div>

      {locations.length === 0 && !showAddForm && (
        <div className="text-center py-8 text-gray-500">
          You haven't added any preferred locations yet.
          {locations.length < maxLocations && (
            <button
              onClick={() => setShowAddForm(true)}
              className="text-primary hover:text-primary-dark ml-2"
            >
              Add your first location
            </button>
          )}
        </div>
      )}

      {locations.length >= maxLocations && !showAddForm && (
        <div className="mt-4 text-center text-gray-600">
          You've reached the maximum number of allowed locations.
          {maxLocations === 3 && (
            <span className="ml-1">
              Upgrade to premium for unlimited locations!
            </span>
          )}
        </div>
      )}
    </div>
  );
};

export default PreferredLocations; 