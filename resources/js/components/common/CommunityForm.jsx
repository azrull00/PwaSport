import React from 'react';
import LocationPicker from './LocationPicker';
import { useFormik } from 'formik';
import * as Yup from 'yup';

const validationSchema = Yup.object({
  name: Yup.string()
    .required('Community name is required')
    .min(3, 'Name must be at least 3 characters')
    .max(50, 'Name must be at most 50 characters'),
  description: Yup.string()
    .required('Description is required')
    .min(10, 'Description must be at least 10 characters'),
  community_type: Yup.string()
    .required('Community type is required')
    .oneOf(['public', 'private', 'invitation_only']),
  skill_level_focus: Yup.string()
    .required('Skill level focus is required')
    .oneOf(['mixed', 'pemula', 'menengah', 'mahir', 'ahli', 'profesional']),
  venue_name: Yup.string().required('Venue name is required'),
  venue_address: Yup.string().required('Venue address is required'),
  venue_city: Yup.string().required('City is required'),
  latitude: Yup.number().required('Location is required'),
  longitude: Yup.number().required('Location is required'),
  regular_schedule: Yup.string(),
  membership_fee: Yup.number().min(0, 'Fee cannot be negative'),
  max_members: Yup.number()
    .min(2, 'Must allow at least 2 members')
    .max(200, 'Cannot exceed 200 members'),
  is_premium_required: Yup.boolean()
});

const CommunityForm = ({
  initialValues,
  onSubmit,
  submitButtonText = 'Save',
  loading = false,
  error = null,
  sports = []
}) => {
  const formik = useFormik({
    initialValues: {
      name: '',
      description: '',
      sport_id: '',
      community_type: 'public',
      skill_level_focus: 'mixed',
      venue_name: '',
      venue_address: '',
      venue_city: '',
      latitude: null,
      longitude: null,
      regular_schedule: '',
      membership_fee: 0,
      max_members: 50,
      is_premium_required: false,
      ...initialValues
    },
    validationSchema,
    onSubmit: async (values) => {
      await onSubmit(values);
    }
  });

  const handleLocationChange = (location) => {
    formik.setFieldValue('latitude', location.lat);
    formik.setFieldValue('longitude', location.lng);
  };

  return (
    <form onSubmit={formik.handleSubmit} className="space-y-6">
      {error && (
        <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
          {error}
        </div>
      )}

      <div className="bg-white rounded-lg shadow-md p-6">
        <h2 className="text-xl font-semibold mb-4">Basic Information</h2>
        
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Community Name
            </label>
            <input
              type="text"
              {...formik.getFieldProps('name')}
              className={`w-full px-3 py-2 border rounded-md ${
                formik.touched.name && formik.errors.name ? 'border-red-500' : ''
              }`}
            />
            {formik.touched.name && formik.errors.name && (
              <div className="text-red-500 text-sm mt-1">{formik.errors.name}</div>
            )}
          </div>

          {sports.length > 0 && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Sport
              </label>
              <select
                {...formik.getFieldProps('sport_id')}
                className={`w-full px-3 py-2 border rounded-md ${
                  formik.touched.sport_id && formik.errors.sport_id ? 'border-red-500' : ''
                }`}
              >
                <option value="">Select a sport</option>
                {sports.map(sport => (
                  <option key={sport.id} value={sport.id}>
                    {sport.name}
                  </option>
                ))}
              </select>
              {formik.touched.sport_id && formik.errors.sport_id && (
                <div className="text-red-500 text-sm mt-1">{formik.errors.sport_id}</div>
              )}
            </div>
          )}

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Community Type
            </label>
            <select
              {...formik.getFieldProps('community_type')}
              className={`w-full px-3 py-2 border rounded-md ${
                formik.touched.community_type && formik.errors.community_type ? 'border-red-500' : ''
              }`}
            >
              <option value="public">Public</option>
              <option value="private">Private</option>
              <option value="invitation_only">Invitation Only</option>
            </select>
            {formik.touched.community_type && formik.errors.community_type && (
              <div className="text-red-500 text-sm mt-1">{formik.errors.community_type}</div>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Skill Level Focus
            </label>
            <select
              {...formik.getFieldProps('skill_level_focus')}
              className={`w-full px-3 py-2 border rounded-md ${
                formik.touched.skill_level_focus && formik.errors.skill_level_focus ? 'border-red-500' : ''
              }`}
            >
              <option value="mixed">Mixed</option>
              <option value="pemula">Beginner</option>
              <option value="menengah">Intermediate</option>
              <option value="mahir">Advanced</option>
              <option value="ahli">Expert</option>
              <option value="profesional">Professional</option>
            </select>
            {formik.touched.skill_level_focus && formik.errors.skill_level_focus && (
              <div className="text-red-500 text-sm mt-1">{formik.errors.skill_level_focus}</div>
            )}
          </div>
        </div>

        <div className="mt-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Description
          </label>
          <textarea
            {...formik.getFieldProps('description')}
            rows={4}
            className={`w-full px-3 py-2 border rounded-md ${
              formik.touched.description && formik.errors.description ? 'border-red-500' : ''
            }`}
          />
          {formik.touched.description && formik.errors.description && (
            <div className="text-red-500 text-sm mt-1">{formik.errors.description}</div>
          )}
        </div>
      </div>

      <div className="bg-white rounded-lg shadow-md p-6">
        <h2 className="text-xl font-semibold mb-4">Location Information</h2>
        
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Venue Name
            </label>
            <input
              type="text"
              {...formik.getFieldProps('venue_name')}
              className={`w-full px-3 py-2 border rounded-md ${
                formik.touched.venue_name && formik.errors.venue_name ? 'border-red-500' : ''
              }`}
            />
            {formik.touched.venue_name && formik.errors.venue_name && (
              <div className="text-red-500 text-sm mt-1">{formik.errors.venue_name}</div>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              City
            </label>
            <input
              type="text"
              {...formik.getFieldProps('venue_city')}
              className={`w-full px-3 py-2 border rounded-md ${
                formik.touched.venue_city && formik.errors.venue_city ? 'border-red-500' : ''
              }`}
            />
            {formik.touched.venue_city && formik.errors.venue_city && (
              <div className="text-red-500 text-sm mt-1">{formik.errors.venue_city}</div>
            )}
          </div>
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Address
          </label>
          <input
            type="text"
            {...formik.getFieldProps('venue_address')}
            className={`w-full px-3 py-2 border rounded-md ${
              formik.touched.venue_address && formik.errors.venue_address ? 'border-red-500' : ''
            }`}
          />
          {formik.touched.venue_address && formik.errors.venue_address && (
            <div className="text-red-500 text-sm mt-1">{formik.errors.venue_address}</div>
          )}
        </div>

        <div className="mb-4">
          <label className="block text-sm font-medium text-gray-700 mb-1">
            Location
          </label>
          <LocationPicker
            onLocationChange={handleLocationChange}
            initialLocation={
              formik.values.latitude && formik.values.longitude
                ? { lat: formik.values.latitude, lng: formik.values.longitude }
                : null
            }
            height="300px"
          />
          {(formik.touched.latitude || formik.touched.longitude) &&
            (formik.errors.latitude || formik.errors.longitude) && (
              <div className="text-red-500 text-sm mt-1">Location is required</div>
            )}
        </div>
      </div>

      <div className="bg-white rounded-lg shadow-md p-6">
        <h2 className="text-xl font-semibold mb-4">Additional Settings</h2>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Regular Schedule
            </label>
            <input
              type="text"
              {...formik.getFieldProps('regular_schedule')}
              placeholder="e.g., Every Monday and Wednesday, 7-9 PM"
              className="w-full px-3 py-2 border rounded-md"
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Maximum Members
            </label>
            <input
              type="number"
              {...formik.getFieldProps('max_members')}
              min="2"
              max="200"
              className={`w-full px-3 py-2 border rounded-md ${
                formik.touched.max_members && formik.errors.max_members ? 'border-red-500' : ''
              }`}
            />
            {formik.touched.max_members && formik.errors.max_members && (
              <div className="text-red-500 text-sm mt-1">{formik.errors.max_members}</div>
            )}
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
              Membership Fee (IDR)
            </label>
            <input
              type="number"
              {...formik.getFieldProps('membership_fee')}
              min="0"
              className={`w-full px-3 py-2 border rounded-md ${
                formik.touched.membership_fee && formik.errors.membership_fee ? 'border-red-500' : ''
              }`}
            />
            {formik.touched.membership_fee && formik.errors.membership_fee && (
              <div className="text-red-500 text-sm mt-1">{formik.errors.membership_fee}</div>
            )}
          </div>

          <div className="flex items-center space-x-2">
            <input
              type="checkbox"
              {...formik.getFieldProps('is_premium_required')}
              className="w-4 h-4 text-primary border-gray-300 rounded focus:ring-primary"
            />
            <label className="text-sm font-medium text-gray-700">
              Premium Membership Required
            </label>
          </div>
        </div>
      </div>

      <div className="flex justify-end">
        <button
          type="submit"
          disabled={loading || !formik.isValid}
          className={`px-4 py-2 rounded-md text-white ${
            loading || !formik.isValid
              ? 'bg-gray-400 cursor-not-allowed'
              : 'bg-primary hover:bg-primary-dark'
          }`}
        >
          {loading ? (
            <div className="flex items-center space-x-2">
              <div className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
              <span>Saving...</span>
            </div>
          ) : (
            submitButtonText
          )}
        </button>
      </div>
    </form>
  );
};

export default CommunityForm; 