<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'community_id' => 'nullable|exists:communities,id',
            'sport_id' => 'required|exists:sports,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'event_type' => 'required|in:mabar,coaching,friendly_match,tournament',
            'event_date' => 'required|date|after:today',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'max_participants' => 'required|integer|min:2|max:50',
            'location_name' => 'required|string|max:255',
            'location_address' => 'required|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'entry_fee' => 'nullable|numeric|min:0|max:1000000',
            'skill_level_required' => 'required|in:pemula,menengah,mahir,ahli,profesional',
            'is_premium_only' => 'nullable|boolean',
            'auto_confirm_participants' => 'nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'sport_id.required' => 'Olahraga wajib dipilih.',
            'sport_id.exists' => 'Olahraga yang dipilih tidak valid.',
            'title.required' => 'Judul event wajib diisi.',
            'title.max' => 'Judul event maksimal 255 karakter.',
            'description.required' => 'Deskripsi event wajib diisi.',
            'description.max' => 'Deskripsi event maksimal 1000 karakter.',
            'event_type.required' => 'Tipe event wajib dipilih.',
            'event_type.in' => 'Tipe event tidak valid.',
            'event_date.required' => 'Tanggal event wajib diisi.',
            'event_date.after' => 'Tanggal event harus setelah hari ini.',
            'start_time.required' => 'Waktu mulai wajib diisi.',
            'end_time.required' => 'Waktu selesai wajib diisi.',
            'end_time.after' => 'Waktu selesai harus setelah waktu mulai.',
            'max_participants.required' => 'Jumlah maksimal peserta wajib diisi.',
            'max_participants.min' => 'Jumlah maksimal peserta minimal 2 orang.',
            'max_participants.max' => 'Jumlah maksimal peserta maksimal 50 orang.',
            'location_name.required' => 'Nama lokasi wajib diisi.',
            'location_address.required' => 'Alamat lokasi wajib diisi.',
            'latitude.between' => 'Latitude harus antara -90 dan 90.',
            'longitude.between' => 'Longitude harus antara -180 dan 180.',
            'entry_fee.min' => 'Biaya masuk tidak boleh negatif.',
            'entry_fee.max' => 'Biaya masuk maksimal Rp 1.000.000.',
            'skill_level_required.required' => 'Level skill wajib dipilih.',
            'skill_level_required.in' => 'Level skill tidak valid.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'community_id' => 'komunitas',
            'sport_id' => 'olahraga',
            'event_type' => 'tipe event',
            'event_date' => 'tanggal event',
            'start_time' => 'waktu mulai',
            'end_time' => 'waktu selesai',
            'max_participants' => 'jumlah maksimal peserta',
            'location_name' => 'nama lokasi',
            'location_address' => 'alamat lokasi',
            'entry_fee' => 'biaya masuk',
            'skill_level_required' => 'level skill yang dibutuhkan',
            'is_premium_only' => 'khusus premium',
            'auto_confirm_participants' => 'konfirmasi otomatis peserta',
        ];
    }
}
