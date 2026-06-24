'use client';

import { useEffect, useState } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import { getPermissions, hasPermission } from '@/src/lib/permissions';

interface ProtectedRouteProps {
  children: React.ReactNode;
}

// Map paths to required permissions
const pathToPermission: Record<string, string> = {
  '/dashboard': 'dashboard',
  '/dashboard/surat': 'surat',
  '/dashboard/sop': 'sop',
  '/dashboard/pks': 'pks',
  '/dashboard/pengaturan': 'settings',
};

export default function ProtectedRoute({ children }: ProtectedRouteProps) {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const router = useRouter();
  const pathname = usePathname();

  useEffect(() => {
    const checkAuth = () => {
      const userSession = localStorage.getItem('user');
      if (userSession) {
        setIsAuthenticated(true);
        // Check permission for current path
        const requiredPermission = pathToPermission[pathname];
        if (requiredPermission && !hasPermission(requiredPermission)) {
          // Redirect to dashboard and show alert
          alert('Akses Ditolak');
          router.push('/dashboard');
        }
      } else {
        router.push('/');
      }
      setIsLoading(false);
    };

    checkAuth();
  }, [router, pathname]);

  if (isLoading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-gray-50">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
          <p className="text-gray-600">Loading...</p>
        </div>
      </div>
    );
  }

  return isAuthenticated ? <>{children}</> : null;
}
