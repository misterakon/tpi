{{-- Onglets de « Rôles d'audience » : les rôles | le planning des salles --}}
<nav class="rl-onglets" aria-label="Rôles d'audience">
  <a href="{{ route('audiences.roles') }}" class="{{ request()->routeIs('audiences.roles.planning') ? '' : 'actif' }}"><i class="fa-solid fa-table-list"></i> Rôles</a>
  <a href="{{ route('audiences.roles.planning') }}" class="{{ request()->routeIs('audiences.roles.planning') ? 'actif' : '' }}"><i class="fa-solid fa-calendar-week"></i> Planning des salles</a>
</nav>
<style>
  .rl-onglets { display: flex; gap: 6px; margin: 0 0 14px; border-bottom: 2px solid #E3E8E5; }
  .rl-onglets a { padding: 9px 16px; font-weight: 600; font-size: 14px; color: #1E2723; text-decoration: none; border-bottom: 3px solid transparent; margin-bottom: -2px; }
  .rl-onglets a:hover { color: #E8590C; }
  .rl-onglets a.actif { color: #E8590C; border-bottom-color: #E8590C; }
</style>
