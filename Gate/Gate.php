<?php

declare(strict_types=1);

namespace System\Gate;

use App\Core\Abstracts\Policy;
use System\Cache\Cache;
use System\Http\Request;
use System\Database\Database;
use System\Gate\GateException;

class Gate {
   public function __construct(
      private Request $request,
      private Database $database,
      private Cache $cache
   ) {
   }

   /**
    * Authorize — throws 403 if denied with specific reason
    */
   public function authorize(Policy $policy, string $ability, array $context = []): void {
      $this->check($policy, $ability, $context);
   }

   public function allows(Policy $policy, string $ability, array $context = []): bool {
      try {
         return $this->check($policy, $ability, $context);
      } catch (GateException $e) {
         return false;
      }
   }

   /**
    * Check if ability is denied
    */
   public function denies(Policy $policy, string $ability, array $context = []): bool {
      try {
         return !$this->check($policy, $ability, $context);
      } catch (GateException $e) {
         return true;
      }
   }

   /**
    * Core authorization check
    *
    * Flow: resolve user → load roles/permissions → before() → ability() → after()
    */
   private function check(Policy $policy, string $ability, array $context = []): bool {
      $user = $this->resolveUser($context);

      if (empty($user)) {
         throw new GateException('User is not authenticated or session expired.', 401);
      }

      // 1. Before hook
      $before = $policy->before($user, $ability);
      if ($before === false) {
         throw new GateException("Unauthorized: Denied by global 'before' rule in " . get_class($policy), 403);
      }
      if ($before === true) {
         return true;
      }

      // 2. Ability method
      if (!method_exists($policy, $ability)) {
         throw new GateException("Unauthorized: Ability method '{$ability}' not found in " . get_class($policy), 403);
      }

      $model = $context['model'] ?? null;
      if ($model !== null) {
         $result = $policy->$ability($user, $model, $context);
      } else {
         $result = $policy->$ability($user, $context);
      }

      if ($result === false) {
         throw new GateException("Unauthorized: Denied by '{$ability}' method in " . get_class($policy), 403);
      }

      // 3. After hook
      $after = $policy->after($user, $ability, $result);
      if ($after === false) {
         throw new GateException("Unauthorized: Denied by global 'after' rule in " . get_class($policy), 403);
      }
      if ($after === true) {
         return true;
      }

      return (bool) $result;
   }

   /**
    * Resolve user with roles and permissions for given context
    */
   private function resolveUser(array $context = []): ?array {
      $jwtUser = $this->request->getUser();

      if (empty($jwtUser) || !isset($jwtUser['id'])) {
         return null;
      }

      $userId = (int) $jwtUser['id'];

      // Simplified cache key (one per user, contains all scopes)
      $cacheKey = $this->getCacheKey($userId);

      $cached = $this->cache->get($cacheKey);
      if ($cached) {
         return $this->filterUserByContext($cached, $context);
      }

      // Load roles and permissions for user
      $data = $this->loadUserData($userId);

      // Cache set
      $expire = (int) import_config('defines.gate')['cache_expire'];
      $this->cache->set($cacheKey, $data, $expire);

      return $this->filterUserByContext($data, $context);
   }

   /**
    * Load all user roles and permissions in one go
    */
   private function loadUserData(int $userId): array {
      $data = [
         'id' => $userId,
         'roles' => [],
         'permissions' => []
      ];

      /*
     |--------------------------------------------------------------------------
     | Roles (NO permission join)
     |--------------------------------------------------------------------------
     */
      $roles = $this->database
         ->prepare("SELECT r.id, r.name, r.slug, ur.scope_type, ur.scope_id
            FROM app_user_role ur
            JOIN app_role r ON r.id = ur.role_id
            WHERE ur.user_id = :user_id
         ")
         ->execute(['user_id' => $userId])
         ->fetchAll();

      $data['roles'] = $roles;

      /*
     |--------------------------------------------------------------------------
     | Permissions from roles (no cartesian explosion)
     |--------------------------------------------------------------------------
     */
      $rolePermissions = $this->database
         ->prepare("SELECT p.id, p.name, p.slug, 'allow' AS type, ur.scope_type, ur.scope_id, 'role' AS source
            FROM app_user_role ur
            JOIN app_role_permission rp ON rp.role_id = ur.role_id
            JOIN app_permission p ON p.id = rp.permission_id
            WHERE ur.user_id = :user_id
         ")
         ->execute(['user_id' => $userId])
         ->fetchAll();

      /*
     |--------------------------------------------------------------------------
     | Direct permissions
     |--------------------------------------------------------------------------
     */
      $directPermissions = $this->database
         ->prepare("SELECT p.id, p.name, p.slug, up.type, up.scope_type, up.scope_id, 'direct' AS source
            FROM app_user_permission up
            JOIN app_permission p ON p.id = up.permission_id
            WHERE up.user_id = :user_id
         ")
         ->execute(['user_id' => $userId])
         ->fetchAll();


      $data['permissions'] = [
         ...$rolePermissions,
         ...$directPermissions
      ];

      return $data;
   }

   /**
    * Filter loaded user data based on current context/scope
    * This recreates the exact structure expected by Policies
    */
   private function filterUserByContext(array $data, array $context): array {
      $scopeType = $context['scope_type'] ?? null;
      $scopeId = $context['scope_id'] ?? null;

      $filteredRoles = [];
      $resolvedPermissions = [];

      // Filter Roles
      foreach ($data['roles'] as $role) {
         if ($this->matchesScope($role['scope_type'], $role['scope_id'], $scopeType, $scopeId)) {
            $filteredRoles[] = $role;
         }
      }

      // Prioritize: Direct Deny > Direct Allow > Role Allow
      foreach ($data['permissions'] as $permission) {
         if (!$this->matchesScope($permission['scope_type'], $permission['scope_id'], $scopeType, $scopeId)) {
            continue;
         }

         $slug = $permission['slug'];
         // If already denied by a direct permission, skip everything else
         if (isset($resolvedPermissions[$slug]) && $resolvedPermissions[$slug]['source'] === 'direct' && $resolvedPermissions[$slug]['type'] === 'deny') {
            continue;
         }

         // If this is a direct deny, it overwrites everything
         if ($permission['source'] === 'direct' && $permission['type'] === 'deny') {
            $resolvedPermissions[$slug] = $permission;
            continue;
         }

         // If this is a direct allow, it overwrites role allow (but not direct deny, which we checked above)
         if ($permission['source'] === 'direct' && $permission['type'] === 'allow') {
            $resolvedPermissions[$slug] = $permission;
            continue;
         }

         // If this is a role allow, add it only if not already set (or if we want to track source, but for simple allow/deny, existence is enough)
         // Note: Logic above handles direct overrides. If we are here, it's either a role permission or a new direct permission being processed.
         if (!isset($resolvedPermissions[$slug])) {
            $resolvedPermissions[$slug] = $permission;
         } elseif ($permission['source'] === 'direct') {
            // Direct always overrides role
            $resolvedPermissions[$slug] = $permission;
         }
      }

      // Final cleanup: Remove denied permissions
      $finalPermissions = [];
      foreach ($resolvedPermissions as $slug => $permission) {
         if ($permission['type'] === 'allow') {
            $finalPermissions[] = $slug;
         }
      }

      return [
         'id' => $data['id'],
         'roles' => array_column($filteredRoles, 'slug'),
         'permissions' => $finalPermissions,
         'roles_raw' => $filteredRoles,
         'permissions_raw' => array_values($resolvedPermissions)
      ];
   }

   /**
    * Scope Matching Logic
    */
   private function matchesScope(?string $itemType, ?int $itemId, ?string $reqType, ?int $reqId): bool {
      // 1. System Scope (Global)
      if ($itemType === 'system' || $itemType === null) {
         return true;
      }

      // 2. Specific Scope
      if ($reqType !== null) {
         // Exact match required
         return $itemType === $reqType && (string)$itemId === (string)$reqId;
      }

      return false;
   }

   /**
    * Clear user permissions cache
    */
   public function clearUserCache(int $userId): void {
      $this->cache->delete($this->getCacheKey($userId));
   }

   private function getCacheKey(int $userId): string {
      return 'gate_user_' . $userId;
   }
}
