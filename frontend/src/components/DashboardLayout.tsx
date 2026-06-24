'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import { getPermissions } from '@/src/lib/permissions';

interface MenuItem {
  id: string;
  label: string;
  href?: string;
  icon: string;
  permission: string;
  dropdown?: {
    id: string;
    label: string;
    href: string;
    permission: string;
  }[];
}

const menuItems: MenuItem[] = [
  { 
    id: 'dashboard', 
    label: 'Dashboard', 
    href: '/dashboard', 
    icon: '📊', 
    permission: 'dashboard' 
  },
  { 
    id: 'legal', 
    label: 'Legal', 
    icon: '📑', 
    permission: 'legal',
    dropdown: [
      { id: 'perjanjian-kerjasama', label: 'Perjanjian Kerjasama (PKS)', href: '/dashboard/pks', permission: 'pks' },
      { id: 'regulasi', label: '› Regulasi', href: '/dashboard/regulasi', permission: 'legal' },
      { id: 'perizinan', label: '› Perizinan', href: '/dashboard/perizinan', permission: 'legal' }
    ]
  },
  { 
    id: 'sekretariat', 
    label: 'Sekretariat', 
    icon: '✉️', 
    permission: 'surat',
    dropdown: [
      { id: 'surat-masuk', label: 'Surat Masuk', href: '/dashboard/surat', permission: 'surat' },
      { id: 'surat-keluar', label: 'Surat Keluar', href: '/dashboard/surat-keluar', permission: 'surat' }
    ]
  },
  { 
    id: 'tenaga-medis', 
    label: 'Tenaga Medis', 
    icon: '👨‍⚕️', 
    permission: 'tenaga-medis',
    dropdown: [
      { id: 'sip', label: 'SIP', href: '/dashboard/sip', permission: 'tenaga-medis' },
      { id: 'str', label: 'STR', href: '/dashboard/str', permission: 'tenaga-medis' }
    ]
  },
  { 
    id: 'akreditasi', 
    label: 'Akreditasi', 
    icon: '🏅', 
    permission: 'akreditasi',
    dropdown: [
      { id: 'akreditasi-progres', label: 'Progress Akreditasi', href: '/dashboard/akreditasi', permission: 'akreditasi' }
    ]
  },
  { 
    id: 'corporate-secretary', 
    label: 'Corporate Secretary', 
    icon: '📋', 
    permission: 'corporate-secretary',
    dropdown: [
      { id: 'rapat', label: 'Rapat', href: '/dashboard/rapat', permission: 'corporate-secretary' }
    ]
  },
  { 
    id: 'sop-sdm', 
    label: 'SOP & SDM', 
    icon: '📚', 
    permission: 'sop',
    dropdown: [
      { id: 'dokumen-sop', label: 'Dokumen SOP', href: '/dashboard/sop', permission: 'sop' }
    ]
  },
  { 
    id: 'setting', 
    label: 'Setting', 
    icon: '⚙️', 
    permission: 'settings',
    dropdown: [
      { id: 'pengaturan', label: 'Pengaturan', href: '/dashboard/pengaturan', permission: 'settings' }
    ]
  }
];

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const router = useRouter();
  const [permissions, setPermissions] = useState<string[]>([]);
  const [user, setUser] = useState<any>({});
  const [openDropdown, setOpenDropdown] = useState<string | null>(null);

  useEffect(() => {
    const userStr = localStorage.getItem('user');
    if (userStr) {
      const userData = JSON.parse(userStr);
      setUser(userData);
      // Give all permissions to Super Admin
      if (userData.role === 'Super Admin') {
        setPermissions([
          'dashboard', 'legal', 'pks', 'surat', 'tenaga-medis', 
          'akreditasi', 'corporate-secretary', 'sop', 'settings'
        ]);
      } else {
        setPermissions(userData.permissions || []);
      }
    }
  }, []);

  // Filter menu items based on permissions
  const filteredMenuItems = menuItems.filter(item => 
    item.dropdown 
      ? item.dropdown.some(dd => permissions.includes(dd.permission))
      : permissions.includes(item.permission)
  );

  const handleLogout = () => {
    localStorage.removeItem('user');
    router.push('/');
  };

  const toggleDropdown = (itemId: string) => {
    if (openDropdown === itemId) {
      setOpenDropdown(null);
    } else {
      setOpenDropdown(itemId);
    }
  };

  return (
    <div className="min-h-screen bg-gray-50 flex">
      {/* Sidebar - Emerald Green */}
      <aside 
        className="w-64 bg-gradient-to-b from-emerald-800 to-emerald-900 text-white shadow-xl"
      >
        <div className="p-6">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 bg-white rounded-lg flex items-center justify-center text-emerald-800 font-bold text-xl">
              🏥
            </div>
            <div>
              <h1 className="text-lg font-bold">RS. Taman Harapan Baru</h1>
              <p className="text-xs text-emerald-200">Legal & Corporate Secretary</p>
            </div>
          </div>
        </div>
        
        <nav className="p-4 space-y-2">
          {filteredMenuItems.map((item) => (
            <div key={item.id} className="space-y-1">
              {item.dropdown ? (
                <>
                  <button
                    onClick={() => toggleDropdown(item.id)}
                    className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                      item.dropdown.some(dd => pathname === dd.href)
                        ? 'bg-emerald-700 text-white'
                        : 'text-emerald-100 hover:bg-emerald-700'
                    }`}
                  >
                    <span className="text-xl">{item.icon}</span>
                    <div className="flex-1 flex justify-between items-center">
                      <span>{item.label}</span>
                      <span className={`transition-transform ${openDropdown === item.id ? 'rotate-180' : ''}`}>
                        ▼
                      </span>
                    </div>
                  </button>
                  {openDropdown === item.id && (
                    <div className="ml-4 space-y-1">
                      {item.dropdown.filter(dd => permissions.includes(dd.permission)).map((dd) => (
                        <Link
                          key={dd.id}
                          href={dd.href}
                          className={`block px-4 py-2 rounded-lg text-sm transition-colors ${
                            pathname === dd.href
                              ? 'bg-emerald-600 text-white font-medium'
                              : 'text-emerald-100 hover:bg-emerald-700'
                          }`}
                        >
                          {dd.label}
                        </Link>
                      ))}
                    </div>
                  )}
                </>
              ) : (
                <Link
                  href={item.href!}
                  className={`flex items-center gap-3 px-4 py-3 rounded-lg transition-colors ${
                    pathname === item.href
                      ? 'bg-emerald-700 text-white font-medium'
                      : 'text-emerald-100 hover:bg-emerald-700'
                  }`}
                >
                  <span className="text-xl">{item.icon}</span>
                  <span>{item.label}</span>
                </Link>
              )}
            </div>
          ))}
        </nav>
      </aside>

      {/* Main Content */}
      <main className="flex-1 flex flex-col">
        {/* Header */}
        <header className="bg-white shadow-sm px-8 py-4 flex justify-between items-center">
          <div className="flex items-center gap-4 flex-1 max-w-md">
            <div className="relative flex-1">
              <input
                type="text"
                placeholder="Cari semua modul..."
                className="w-full pl-10 pr-4 py-2 bg-gray-100 border-0 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:bg-white transition-all"
              />
              <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
            </div>
          </div>
          
          <div className="flex items-center gap-4">
            <button className="text-gray-500 hover:text-emerald-600 transition-colors text-xl">
              🔔
            </button>
            <div className="flex items-center gap-3 bg-emerald-50 px-4 py-2 rounded-xl">
              <div className="w-10 h-10 bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-full flex items-center justify-center text-white font-bold text-lg">
                {user.name?.charAt(0) || 'U'}
              </div>
              <div className="text-left">
                <p className="text-sm font-semibold text-gray-800">{user.name || 'User'}</p>
                <p className="text-xs text-gray-500">{user.role || ''}</p>
              </div>
              <div className="relative">
                <button className="text-gray-400 hover:text-gray-600 transition-colors">
                  ▼
                </button>
                <div className="absolute right-0 top-full mt-2 bg-white shadow-xl rounded-xl py-2 w-48 hidden group-hover:block">
                  <button
                    onClick={handleLogout}
                    className="w-full text-left px-4 py-2 hover:bg-red-50 hover:text-red-600 transition-colors"
                  >
                    Logout
                  </button>
                </div>
              </div>
            </div>
          </div>
        </header>
        
        {/* Page Content */}
        <div className="flex-1 p-8 overflow-y-auto">
          {children}
        </div>
      </main>
    </div>
  );
}