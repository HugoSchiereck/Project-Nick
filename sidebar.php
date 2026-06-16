<?php
// sidebar.php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <div class="sidebar-logo-wrap">
    <img src="https://mstlogistics.nl/assets/images/logo/logo.svg" alt="MST Logistics">
  </div>

  <?php if ($currentUser['role'] === 'employee'): ?>
  <div class="nav-section">
    <span class="nav-label">Mijn Portaal</span>
    <a href="dashboard.php" class="nav-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
    <a href="mijn_verlof.php" class="nav-item <?= $currentPage == 'mijn_verlof.php' ? 'active' : '' ?>">Mijn verlofaanvragen</a>
    <a href="mijn_code95.php" class="nav-item <?= $currentPage == 'mijn_code95.php' ? 'active' : '' ?>">Mijn Code 95</a>
    <a href="mijn_tcvt.php" class="nav-item <?= $currentPage == 'mijn_tcvt.php' ? 'active' : '' ?>">Mijn TCVT</a>
  </div>
  <?php endif; ?>

  <?php if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'manager'): ?>
  <div class="nav-section">
    <span class="nav-label">Overzicht</span>
    <a href="dashboard.php" class="nav-item <?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">Dashboard</a>
  </div>

  <div class="nav-section">
    <span class="nav-label">HR & Verlof</span>
    <a href="medewerkers.php" class="nav-item <?= $currentPage == 'medewerkers.php' || $currentPage == 'medewerker_toevoegen.php' ? 'active' : '' ?>">Medewerkers</a>
    <a href="verlof_beheer.php" class="nav-item <?= $currentPage == 'verlof_beheer.php' ? 'active' : '' ?>">Verlofaanvragen</a>
  </div>

  <div class="nav-section">
    <span class="nav-label">Certificering</span>
    <a href="code95.php" class="nav-item <?= $currentPage == 'code95.php' ? 'active' : '' ?>">Code 95</a>
    <a href="cursussen.php" class="nav-item <?= $currentPage == 'cursussen.php' ? 'active' : '' ?>">Cursussen</a>
    <a href="tcvt.php" class="nav-item <?= $currentPage == 'tcvt.php' ? 'active' : '' ?>">TCVT</a>
    <a href="opleidingen.php" class="nav-item <?= $currentPage == 'opleidingen.php' ? 'active' : '' ?>">Opleidingen</a>
  </div>

  <div class="nav-section" style="margin-top:20px;">
    <span class="nav-label">Beheer</span>
    <a href="onboarding.php" class="nav-item <?= $currentPage == 'onboarding.php' ? 'active' : '' ?>">Onboarding</a>
    <a href="instellingen.php" class="nav-item <?= $currentPage == 'instellingen.php' ? 'active' : '' ?>">Instellingen</a>
  </div>
  <?php endif; ?>

  <div class="sidebar-bottom">
    <a href="logout.php" class="user-chip">
      <div class="avatar"><?= htmlspecialchars(substr($currentUser['first_name'] ?? 'U', 0, 1)) ?></div>
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;font-weight:500;"><?= htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?></div>
        <div style="font-size:11px;color:var(--text3);"><?= htmlspecialchars(ucfirst($currentUser['role'] ?? 'Gast')) ?></div>
      </div>
    </a>
  </div>
</aside>