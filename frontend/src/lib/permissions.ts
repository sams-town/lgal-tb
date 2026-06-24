export const getPermissions = (): string[] => {
  if (typeof window === 'undefined') return [];
  const user = localStorage.getItem('user');
  if (!user) return [];
  const userData = JSON.parse(user);
  return userData.permissions || [];
};

export const hasPermission = (permission: string): boolean => {
  const permissions = getPermissions();
  return permissions.includes(permission);
};
