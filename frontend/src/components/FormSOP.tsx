'use client';

import { useState } from 'react';

interface FormData {
  judul: string;
  nomorSOP: string;
  unitKerja: string;
  tanggalTerbit: string;
  tanggalExpired: string;
  file: File | null;
}

export default function FormSOP() {
  const [formData, setFormData] = useState<FormData>({
    judul: '',
    nomorSOP: '',
    unitKerja: '',
    tanggalTerbit: new Date().toISOString().split('T')[0],
    tanggalExpired: '',
    file: null,
  });

  const [errors, setErrors] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(false);
  const [success, setSuccess] = useState<string | null>(null);

  const apiBaseUrl = process.env.NEXT_PUBLIC_API_BASE_URL || '/new-hospital/dist/api';

  const validate = (): boolean => {
    const newErrors: Record<string, string> = {};

    if (!formData.judul.trim()) {
      newErrors.judul = 'Judul SOP wajib diisi';
    }
    if (!formData.nomorSOP.trim()) {
      newErrors.nomorSOP = 'Nomor SOP wajib diisi';
    }
    if (!formData.unitKerja.trim()) {
      newErrors.unitKerja = 'Unit kerja wajib diisi';
    }
    if (!formData.tanggalTerbit) {
      newErrors.tanggalTerbit = 'Tanggal terbit wajib diisi';
    }
    if (!formData.file) {
      newErrors.file = 'File SOP wajib diunggah';
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSuccess(null);
    setErrors({});

    if (!validate()) {
      return;
    }

    setLoading(true);

    try {
      const formDataToSend = new FormData();
      formDataToSend.append('judul', formData.judul);
      formDataToSend.append('nomorSOP', formData.nomorSOP);
      formDataToSend.append('unitKerja', formData.unitKerja);
      formDataToSend.append('tanggalTerbit', formData.tanggalTerbit);
      if (formData.tanggalExpired) {
        formDataToSend.append('tanggalExpired', formData.tanggalExpired);
      }
      if (formData.file) {
        formDataToSend.append('file', formData.file);
      }

      const res = await fetch(`${apiBaseUrl}/save_sop.php`, {
        method: 'POST',
        body: formDataToSend,
      });

      const data = await res.json();

      if (res.ok && data.status === 'success') {
        setSuccess('Dokumen SOP berhasil disimpan!');
        setFormData({
          judul: '',
          nomorSOP: '',
          unitKerja: '',
          tanggalTerbit: new Date().toISOString().split('T')[0],
          tanggalExpired: '',
          file: null,
        });
        setErrors({});
      } else {
        setErrors({ submit: data.message || 'Gagal menyimpan dokumen SOP' });
      }
    } catch (err) {
      setErrors({ submit: 'Terjadi kesalahan, silakan coba lagi' });
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => {
    const { name, value, files } = e.target as any;
    setFormData((prev) => ({
      ...prev,
      [name]: files ? files[0] : value,
    }));
    if (errors[name]) {
      setErrors((prev) => ({ ...prev, [name]: '' }));
    }
  };

  return (
    <div className="max-w-3xl mx-auto bg-white rounded-xl shadow-sm p-8">
      <div className="mb-6">
        <h2 className="text-2xl font-bold text-gray-800">Formulir Dokumen SOP</h2>
        <p className="text-gray-600">Isi data dokumen SOP RS dengan lengkap</p>
      </div>

      {success && (
        <div className="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
          {success}
        </div>
      )}

      {errors.submit && (
        <div className="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
          {errors.submit}
        </div>
      )}

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Judul */}
        <div>
          <label htmlFor="judul" className="block text-sm font-medium text-gray-700 mb-2">
            Judul SOP
          </label>
          <input
            type="text"
            id="judul"
            name="judul"
            value={formData.judul}
            onChange={handleChange}
            className={`w-full px-4 py-3 border rounded-lg outline-none transition-colors ${
              errors.judul ? 'border-red-300 focus:ring-2 focus:ring-red-500' : 'border-gray-300 focus:ring-2 focus:ring-blue-500'
            }`}
            placeholder="Masukkan judul SOP"
          />
          {errors.judul && <p className="mt-1 text-sm text-red-600">{errors.judul}</p>}
        </div>

        {/* Nomor SOP */}
        <div>
          <label htmlFor="nomorSOP" className="block text-sm font-medium text-gray-700 mb-2">
            Nomor SOP
          </label>
          <input
            type="text"
            id="nomorSOP"
            name="nomorSOP"
            value={formData.nomorSOP}
            onChange={handleChange}
            className={`w-full px-4 py-3 border rounded-lg outline-none transition-colors ${
              errors.nomorSOP ? 'border-red-300 focus:ring-2 focus:ring-red-500' : 'border-gray-300 focus:ring-2 focus:ring-blue-500'
            }`}
            placeholder="Masukkan nomor SOP"
          />
          {errors.nomorSOP && <p className="mt-1 text-sm text-red-600">{errors.nomorSOP}</p>}
        </div>

        {/* Unit Kerja */}
        <div>
          <label htmlFor="unitKerja" className="block text-sm font-medium text-gray-700 mb-2">
            Unit Kerja
          </label>
          <select
            id="unitKerja"
            name="unitKerja"
            value={formData.unitKerja}
            onChange={handleChange}
            className={`w-full px-4 py-3 border rounded-lg outline-none transition-colors ${
              errors.unitKerja ? 'border-red-300 focus:ring-2 focus:ring-red-500' : 'border-gray-300 focus:ring-2 focus:ring-blue-500'
            }`}
          >
            <option value="">Pilih unit kerja</option>
            <option value="admin">Administrasi</option>
            <option value="medis">Medis</option>
            <option value="keperawatan">Keperawatan</option>
            <option value="farmasi">Farmasi</option>
            <option value="rehabilitasi">Rehabilitasi Medis</option>
            <option value="penunjang">Penunjang Medis</option>
            <option value="keuangan">Keuangan</option>
            <option value="sdm">SDM</option>
            <option value="it">IT</option>
            <option value="legal">Legal & Sekretariat</option>
          </select>
          {errors.unitKerja && <p className="mt-1 text-sm text-red-600">{errors.unitKerja}</p>}
        </div>

        {/* Tanggal Terbit */}
        <div>
          <label htmlFor="tanggalTerbit" className="block text-sm font-medium text-gray-700 mb-2">
            Tanggal Terbit
          </label>
          <input
            type="date"
            id="tanggalTerbit"
            name="tanggalTerbit"
            value={formData.tanggalTerbit}
            onChange={handleChange}
            className={`w-full px-4 py-3 border rounded-lg outline-none transition-colors ${
              errors.tanggalTerbit ? 'border-red-300 focus:ring-2 focus:ring-red-500' : 'border-gray-300 focus:ring-2 focus:ring-blue-500'
            }`}
          />
          {errors.tanggalTerbit && <p className="mt-1 text-sm text-red-600">{errors.tanggalTerbit}</p>}
        </div>

        {/* Tanggal Expired */}
        <div>
          <label htmlFor="tanggalExpired" className="block text-sm font-medium text-gray-700 mb-2">
            Tanggal Kadaluarsa (Opsional)
          </label>
          <input
            type="date"
            id="tanggalExpired"
            name="tanggalExpired"
            value={formData.tanggalExpired}
            onChange={handleChange}
            className="w-full px-4 py-3 border border-gray-300 rounded-lg outline-none focus:ring-2 focus:ring-blue-500"
          />
        </div>

        {/* File Upload */}
        <div>
          <label htmlFor="file" className="block text-sm font-medium text-gray-700 mb-2">
            File Dokumen SOP (PDF/DOC/DOCX)
          </label>
          <input
            type="file"
            id="file"
            name="file"
            accept=".pdf,.doc,.docx"
            onChange={handleChange}
            className={`w-full px-4 py-3 border border-dashed rounded-lg ${
              errors.file ? 'border-red-300' : 'border-gray-300'
            }`}
          />
          {formData.file && (
            <p className="mt-2 text-sm text-gray-600">File terpilih: {formData.file.name}</p>
          )}
          {errors.file && <p className="mt-1 text-sm text-red-600">{errors.file}</p>}
        </div>

        {/* Submit Button */}
        <button
          type="submit"
          disabled={loading}
          className="w-full bg-blue-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {loading ? 'Menyimpan...' : 'Simpan Dokumen SOP'}
        </button>
      </form>
    </div>
  );
}
