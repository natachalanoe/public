        <!-- Layout container -->
        <div class="layout-page">
          <!-- Navbar -->

          <nav
            class="layout-navbar container-fluid navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
            id="layout-navbar">
            <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
              <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                <i class="bi bi-list icon-md"></i>
              </a>
            </div>

            <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
              <div class="navbar-nav align-items-center">
                <div class="nav-item dropdown me-2 me-xl-0">
                  <a
                    class="nav-link dropdown-toggle hide-arrow"
                    id="nav-theme"
                    href="javascript:void(0);"
                    data-bs-toggle="dropdown">
                    <i class="bi bi-sun icon-md theme-icon-active"></i>
                    <span class="d-none ms-2" id="nav-theme-text">Toggle theme</span>
                  </a>
                  <ul class="dropdown-menu dropdown-menu-start" aria-labelledby="nav-theme-text">
                    <li>
                      <button
                        type="button"
                        class="dropdown-item align-items-center active"
                        data-bs-theme-value="light"
                        aria-pressed="false">
                        <span><i class="bi bi-sun icon-md me-3" data-icon="sun"></i>Clair</span>
                      </button>
                    </li>
                    <li>
                      <button
                        type="button"
                        class="dropdown-item align-items-center"
                        data-bs-theme-value="dark"
                        aria-pressed="true">
                        <span><i class="bi bi-moon icon-md me-3" data-icon="moon"></i>Sombre</span>
                      </button>
                    </li>
                    <li style="display: none;">
                      <button
                        type="button"
                        class="dropdown-item align-items-center"
                        data-bs-theme-value="system"
                        aria-pressed="false">
                        <span><i class="bi bi-display icon-md me-3" data-icon="desktop"></i>System</span>
                      </button>
                    </li>
                    <li>
                      <button
                        type="button"
                        class="dropdown-item align-items-center"
                        data-bs-theme-value="semi-dark"
                        aria-pressed="false">
                        <span><i class="bi bi-layout-sidebar-reverse icon-md me-3" data-icon="layout-sidebar-reverse"></i>Menu sombre</span>
                      </button>
                    </li>
                  </ul>
                </div>
              </div>

              
              <div class="navbar-nav-right d-flex align-items-center justify-content-end">
                <ul class="navbar-nav ms-lg-auto">
                  <li class="nav-item me-3">
                    <span class="badge bg-warning text-dark">
                      <i class="bi bi-code-slash me-1"></i>Version en cours de développement 0.8.2
                    </span>
                  </li>
                  <li class="nav-item">
                    <a class="nav-link" href="javascript:void(0)"><i class="navbar-icon bi bi-person"></i> <?php echo ($_SESSION['user']['first_name'] ?? '') . ' ' . ($_SESSION['user']['last_name'] ?? ''); ?></a>
                  </li>
                </ul>
              </div>


            </div>
          </nav>

          <!-- / Navbar -->

          <!-- Content wrapper -->
          <div class="content-wrapper"> 