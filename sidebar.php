<?php
// sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<style>
/* Zorg dat de uitlog-knop altijd de juiste styling heeft, op elke pagina */
.sidebar-bottom { margin-top: auto; padding: 0 10px; }
.user-chip { display: flex; align-items: center; gap: 9px; padding: 9px; border-radius: 8px; border: 1px solid var(--border); text-decoration: none !important; color: var(--text) !important; transition: background .12s; }
.user-chip:hover { background: var(--surface2); }
.avatar { width: 28px; height: 28px; border-radius: 50%; background: var(--accent-light); display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 600; color: var(--accent); flex-shrink: 0; }
</style>

<aside class="sidebar">
  <div class="sidebar-logo-wrap">
    <img src="https://mstlogistics.nl/assets/images/logo/logo.svg" alt="MST Logistics">
  </div>

  <div class="nav-section">
    <span class="nav-label">Mijn Portaal</span>
    <a href="dashboard.php" class="nav-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
    <a href="mijn_verlof.php" class="nav-item <?= $currentPage == 'mijn_verlof.php' ? 'active' : '' ?>">Mijn verlofaanvragen</a>
    <a href="mijn_code95.php" class="nav-item <?= $currentPage == 'mijn_code95.php' ? 'active' : '' ?>">Mijn Code 95</a>
    <a href="mijn_tcvt.php" class="nav-item <?= $currentPage == 'mijn_tcvt.php' ? 'active' : '' ?>">Mijn TCVT</a>
  </div>

  <?php if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'manager'): ?>
  <div class="nav-section" style="margin-top:20px;">
    <span class="nav-label">Beheer — HR & Verlof</span>
    <a href="medewerkers.php" class="nav-item <?= $currentPage == 'medewerkers.php' || $currentPage == 'medewerker_toevoegen.php' ? 'active' : '' ?>">Medewerkers</a>
    <a href="verlof_beheer.php" class="nav-item <?= $currentPage == 'verlof_beheer.php' ? 'active' : '' ?>">Verlofaanvragen</a>
  </div>

  <div class="nav-section">
    <span class="nav-label">Beheer — Certificering</span>
    <a href="code95.php" class="nav-item <?= $currentPage == 'code95.php' ? 'active' : '' ?>">Code 95</a>
    <a href="cursussen.php" class="nav-item <?= $currentPage == 'cursussen.php' ? 'active' : '' ?>">Cursussen</a>
    <a href="tcvt.php" class="nav-item <?= $currentPage == 'tcvt.php' ? 'active' : '' ?>">TCVT</a>
    <a href="opleidingen.php" class="nav-item <?= $currentPage == 'opleidingen.php' ? 'active' : '' ?>">Opleidingen</a>
  </div>

  <div class="nav-section" style="margin-top:20px;">
    <span class="nav-label">Systeem</span>
    <a href="onboarding.php" class="nav-item <?= $currentPage == 'onboarding.php' ? 'active' : '' ?>">Onboarding</a>
    <a href="instellingen.php" class="nav-item <?= $currentPage == 'instellingen.php' ? 'active' : '' ?>">Instellingen</a>
    <a href="2fa_instellen.php" class="nav-item <?= $currentPage == '2fa_instellen.php' ? 'active' : '' ?>">Beveiliging (2FA)</a>
    <a href="auditlog.php" class="nav-item <?= $currentPage == 'auditlog.php' ? 'active' : '' ?>">Wijzigingslog</a>
  </div>
  <?php endif; ?>

  <div class="sidebar-bottom">
    <a href="logout.php" class="user-chip" title="Uitloggen">
      <div class="avatar"><?= htmlspecialchars(substr($currentUser['first_name'] ?? 'U', 0, 1)) ?></div>
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;font-weight:500;"><?= htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?></div>
        <div style="font-size:11px;color:var(--text3);"><?= htmlspecialchars(ucfirst($currentUser['role'] ?? 'Gast')) ?></div>
      </div>
    </a>
  </div>
</aside>