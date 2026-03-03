<?php
/**
 * navbar_core.php
 * Barra de navegación reutilizable para Core Scope
 * Muestra información del usuario y navegación rápida
 */

// Este archivo asume que auth_guard.php ya fue incluido y las variables del usuario están disponibles
if (!isset($user_name)) {
    $user_name = 'Usuario';
    $user_rol = 'N/A';
    $user_foto = '';
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="core-navbar">
    <div class="nav-brand">
        <a href="menu_principal.php" class="brand-link">
            <div class="brand-logo"></div>
            <span class="brand-name">CORE SCOPE</span>
        </a>
    </div>
    
    <div class="nav-actions">
        <a href="menu_principal.php" class="nav-btn" title="Menú Principal">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </a>
        
        <a href="../CGL/dashboard.php" class="nav-btn" title="Dashboard CGL">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                <polyline points="9 22 9 12 15 12 15 22"></polyline>
            </svg>
        </a>
        
        <div class="nav-user">
            <?php if ($user_foto && file_exists('../CGL/' . $user_foto)): ?>
                <img src="../CGL/<?= htmlspecialchars($user_foto) ?>" alt="Avatar" class="user-avatar-small">
            <?php else: ?>
                <div class="user-avatar-small user-avatar-default"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
            <?php endif; ?>
            <span class="user-name-small"><?= htmlspecialchars($user_name) ?></span>
        </div>
        
        <a href="../CGL/logout.php" class="nav-btn nav-btn-logout" title="Cerrar Sesión">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                <polyline points="16 17 21 12 16 7"></polyline>
                <line x1="21" y1="12" x2="9" y2="12"></line>
            </svg>
        </a>
    </div>
</div>

<style>
.core-navbar {
    background: linear-gradient(135deg, #0171e2, #000F9F);
    color: white;
    padding: 12px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    position: sticky;
    top: 0;
    z-index: 1000;
}

.nav-brand {
    display: flex;
    align-items: center;
}

.brand-link {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: white;
    transition: opacity 0.3s;
}

.brand-link:hover {
    opacity: 0.9;
}

.brand-logo {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.25);
    position: relative;
    overflow: hidden;
}

.brand-logo:after {
    content: "";
    position: absolute;
    inset: auto -12px -12px auto;
    width: 24px;
    height: 24px;
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.4);
    transform: rotate(18deg);
}

.brand-name {
    font-weight: 800;
    font-size: 1.1rem;
    letter-spacing: 0.5px;
}

.nav-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.15);
    color: white;
    text-decoration: none;
    transition: all 0.3s;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.nav-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-2px);
}

.nav-btn-logout {
    background: rgba(239, 68, 68, 0.3);
    border-color: rgba(239, 68, 68, 0.4);
}

.nav-btn-logout:hover {
    background: rgba(239, 68, 68, 0.5);
}

.nav-user {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 14px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 999px;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.user-avatar-small {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.5);
}

.user-avatar-default {
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    background: rgba(255, 255, 255, 0.3);
}

.user-name-small {
    font-weight: 600;
    font-size: 0.9rem;
    display: none;
}

@media (min-width: 640px) {
    .user-name-small {
        display: inline;
    }
}

@media (max-width: 640px) {
    .core-navbar {
        padding: 10px 12px;
    }
    
    .brand-name {
        display: none;
    }
    
    .nav-actions {
        gap: 4px;
    }
    
    .nav-btn {
        width: 36px;
        height: 36px;
    }
}
</style>
