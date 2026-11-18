<template>
  <div class="main-header tn">
    <div class="logo">
      <router-link to="/app/dashboard">
        <img :src="'/images/' + currentUser.logo" alt="" @error="$event.target.style.visibility = 'hidden'" />
      </router-link>
    </div>

    <button type="button" class="tn-toggle" :title="$t('PosNext.menu')" @click="sideBarToggle">
      <svg viewBox="0 0 24 24" class="tn-svg" aria-hidden="true">
        <path d="M21 5H3M21 12H11M21 19H3M7 9l-3 3 3 3" />
      </svg>
    </button>

    <div style="margin: auto"></div>

    <div class="header-part-right tn-right">
      <!-- POS -->
      <router-link
        v-if="can('Pos_view')"
        to="/app/pos"
        class="tn-pos"
        :title="$t('PosNext.title')"
      >
        <svg viewBox="0 0 24 24" class="tn-svg" aria-hidden="true">
          <rect x="4" y="3" width="16" height="18" rx="2" />
          <path d="M8 7h8M8 11h2M14 11h2M8 15h2M14 15h2" />
        </svg>
        <span>{{ $t('PosNext.pos_button') }}</span>
      </router-link>

      <!-- Today's summary (drawer): administrators only -->
      <button
        v-if="can('Sales_profit')"
        type="button"
        class="tn-icon tn-hide-xs"
        :class="{ 'is-active': summaryOpen }"
        :title="$t('DaySummary.title_today')"
        v-b-tooltip.hover.bottom
        @click="summaryOpen = true"
      >
        <svg viewBox="0 0 24 24" class="tn-svg" aria-hidden="true">
          <path d="M21.2 15.9A10 10 0 1 1 8 2.8" />
          <path d="M22 12A10 10 0 0 0 12 2v10z" />
        </svg>
      </button>

      <!-- Fullscreen -->
      <button type="button" class="tn-icon tn-hide-xs" :title="$t('PosNext.fullscreen')" @click="handleFullScreen">
        <svg viewBox="0 0 24 24" class="tn-svg" aria-hidden="true">
          <path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5" />
          <path d="M4 4l5 5M20 4l-5 5M20 20l-5-5M4 20l5-5" />
        </svg>
      </button>

      <!-- Language -->
      <b-dropdown variant="link" no-caret right toggle-class="tn-lang-btn" class="tn-dd">
        <template #button-content>
          <span class="tn-flag" v-html="flagSvg($i18n.locale)"></span>
          <span class="tn-lang-code">{{ ($i18n.locale || 'en').toUpperCase() }}</span>
        </template>
        <div class="lang-menu">
          <a
            v-for="lang in languageOptions"
            :key="lang.code"
            href="#"
            class="lang-item"
            :class="{ active: $i18n.locale === lang.code }"
            @click.prevent="SetLocal(lang.code)"
          >
            <span class="tn-flag" v-html="flagSvg(lang.code)"></span>
            <span class="lang-name">{{ lang.name }}</span>
            <i v-if="$i18n.locale === lang.code" class="i-Yes lang-check"></i>
          </a>
        </div>
      </b-dropdown>

      <!-- Notifications -->
      <b-dropdown variant="link" no-caret right toggle-class="tn-icon" class="tn-dd" menu-class="tn-notif-menu">
        <template #button-content>
          <svg viewBox="0 0 24 24" class="tn-svg" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.7 21a2 2 0 0 1-3.4 0" />
          </svg>
          <span v-if="notifs_alert > 0" class="tn-badge">{{ notifs_alert > 99 ? '99+' : notifs_alert }}</span>
        </template>
        <b-dropdown-header>{{ $t('PosNext.notifications') }}</b-dropdown-header>
        <router-link
          v-if="notifs_alert > 0 && can('Reports_quantity_alerts')"
          to="/app/reports/quantity_alerts"
          class="dropdown-item tn-notif"
        >
          <span class="tn-notif-icon">
            <svg viewBox="0 0 24 24" class="tn-svg" aria-hidden="true">
              <path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" />
              <path d="M12 9v4M12 17h.01" />
            </svg>
          </span>
          <span>{{ notifs_alert }} {{ $t('ProductQuantityAlerts') }}</span>
        </router-link>
        <b-dropdown-text v-else class="tn-notif-empty">{{ $t('PosNext.no_notifications') }}</b-dropdown-text>
      </b-dropdown>

      <!-- User -->
      <b-dropdown variant="link" no-caret right toggle-class="tn-user-btn" class="tn-dd tn-user">
        <template #button-content>
          <span class="tn-avatar">{{ userInitial }}</span>
          <span class="tn-user-name">{{ currentUser.username }}</span>
          <svg viewBox="0 0 24 24" class="tn-svg tn-caret" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
        </template>
        <b-dropdown-header>
          <i class="i-Lock-User mr-1"></i> <span>{{ currentUser.username }}</span>
        </b-dropdown-header>
        <b-dropdown-item to="/app/profile">{{ $t('profil') }}</b-dropdown-item>
        <b-dropdown-item v-if="can('setting_system')" to="/app/settings/System_settings">{{ $t('Settings') }}</b-dropdown-item>
        <b-dropdown-divider></b-dropdown-divider>
        <b-dropdown-item @click="logoutUser">{{ $t('logout') }}</b-dropdown-item>
      </b-dropdown>
    </div>

    <day-summary :open="summaryOpen" @close="summaryOpen = false" />
  </div>

  <!-- header top menu end -->
</template>
<script>
import Util from "./../../../utils";
// import Sidebar from "./Sidebar";
import { isMobile } from "mobile-device-detect";
import { mapGetters, mapActions } from "vuex";
import { mixin as clickaway } from "vue-clickaway";
import DaySummary from "./DaySummary.vue";
// import { setTimeout } from 'timers';

export default {
  mixins: [clickaway],
  components: { DaySummary },

  data() {
  
    return {
      langs: [
        "en",
        "fr",
        "ar"
      ],
      // Each language shown in its own name
      languageOptions: [
        { code: "ar", name: "العربية" },
        { code: "fr", name: "Français" },
        { code: "en", name: "English" }
      ],
      
      isDisplay: true,
      isStyle: true,
      isSearchOpen: false,
      isMouseOnMegaMenu: true,
      isMegaMenuOpen: false,
      is_Load:false,
      summaryOpen: false,
     
    };
  },
 
   computed: {
     
     ...mapGetters([
       "currentUser",
      "getSideBarToggleProperties",
      "currentUserPermissions",
      "notifs_alert",
    ]),

    userInitial() {
      const name = this.currentUser && this.currentUser.username ? this.currentUser.username : "?";
      return name.trim().charAt(0).toUpperCase();
    },


  },

  methods: {
    
    ...mapActions([
      "changeSecondarySidebarProperties",
      "changeSidebarProperties",
      "changeThemeMode",
      "logout",
    ]),

    can(permission) {
      return !!(this.currentUserPermissions && this.currentUserPermissions.includes(permission));
    },

    // small inline flags (no image files needed): ar = Morocco, fr = France, en = United Kingdom
    flagSvg(code) {
      if (code === "ar") {
        return '<svg viewBox="0 0 30 20"><rect width="30" height="20" fill="#c1272d"/><path d="M15 5L17.94 14.05 10.25 8.46H19.75L12.06 14.05Z" fill="none" stroke="#006233" stroke-width="0.9"/></svg>';
      }
      if (code === "fr") {
        return '<svg viewBox="0 0 30 20"><rect width="10" height="20" fill="#002395"/><rect x="10" width="10" height="20" fill="#fff"/><rect x="20" width="10" height="20" fill="#ed2939"/></svg>';
      }
      return '<svg viewBox="0 0 60 40" preserveAspectRatio="none"><rect width="60" height="40" fill="#012169"/><path d="M0 0L60 40M60 0L0 40" stroke="#fff" stroke-width="8"/><path d="M0 0L60 40M60 0L0 40" stroke="#C8102E" stroke-width="3"/><path d="M30 0V40M0 20H60" stroke="#fff" stroke-width="12"/><path d="M30 0V40M0 20H60" stroke="#C8102E" stroke-width="7"/></svg>';
    },

    SetLocal(locale) {
      this.$i18n.locale = locale;
      this.$store.dispatch("language/setLanguage", locale);
      Fire.$emit("ChangeLanguage");
    },

    handleFullScreen() {
      Util.toggleFullScreen();
    },
    logoutUser() {
      this.logout();
    },

    closeMegaMenu() {
      this.isMegaMenuOpen = false;
    },
    toggleMegaMenu() {
      this.isMegaMenuOpen = !this.isMegaMenuOpen;
    },
    toggleSearch() {
      this.isSearchOpen = !this.isSearchOpen;
    },

    sideBarToggle(el) {
      if (
        this.getSideBarToggleProperties.isSideNavOpen &&
        this.getSideBarToggleProperties.isSecondarySideNavOpen &&
        isMobile
      ) {
        this.changeSidebarProperties();
        this.changeSecondarySidebarProperties();
      } else if (
        this.getSideBarToggleProperties.isSideNavOpen &&
        this.getSideBarToggleProperties.isSecondarySideNavOpen
      ) {
        this.changeSecondarySidebarProperties();
      } else if (this.getSideBarToggleProperties.isSideNavOpen) {
        this.changeSidebarProperties();
      } else if (
        !this.getSideBarToggleProperties.isSideNavOpen &&
        !this.getSideBarToggleProperties.isSecondarySideNavOpen &&
        !this.getSideBarToggleProperties.isActiveSecondarySideNav
      ) {
        this.changeSidebarProperties();
      } else if (
        !this.getSideBarToggleProperties.isSideNavOpen &&
        !this.getSideBarToggleProperties.isSecondarySideNavOpen
      ) {

        this.changeSidebarProperties();
        this.changeSecondarySidebarProperties();
      }
    }
  }
};
</script>




<style scoped>
/* ---- header layout ---- */
.tn {
  border-bottom: 1px solid #ececf2;
  box-shadow: none;
}
/* logo block: same width as the sidebar, separated like the sidebar */
.tn .logo {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  border-right: 1px solid #ececf2;
}
.tn .logo a { display: flex; align-items: center; justify-content: center; }
.tn .logo img {
  width: auto;
  height: auto;
  max-width: 88px;
  max-height: 40px;
  object-fit: contain;
}
.tn-svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
  flex: 0 0 auto;
}
.tn-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  margin-left: 12px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: #47404f;
  cursor: pointer;
}
.tn-toggle:hover { background: #f3f0f8; color: #663399; }
.tn-toggle .tn-svg { width: 22px; height: 22px; }
.tn-right {
  gap: 4px;
  padding-right: 16px;
}

/* ---- POS button ---- */
.tn-pos {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 38px;
  padding: 0 16px;
  margin-right: 8px;
  border-radius: 999px;
  background: #663399;
  color: #fff !important;
  font-size: 15px;
  font-weight: 600;
  text-decoration: none !important;
  box-shadow: 0 2px 8px rgba(102, 51, 153, .22);
  transition: background-color .15s, box-shadow .15s;
}
.tn-pos:hover { background: #57288a; box-shadow: 0 4px 12px rgba(102, 51, 153, .3); }
.tn-pos .tn-svg { width: 18px; height: 18px; }

/* ---- icon buttons (also used as dropdown toggles) ---- */
.tn-icon,
.tn >>> .tn-icon {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  padding: 0;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: #47404f;
  cursor: pointer;
  text-decoration: none;
}
.tn-icon:hover,
.tn >>> .tn-icon:hover,
.tn >>> .show > .tn-icon,
.tn-icon.router-link-active,
.tn-icon.is-active { background: #f3f0f8; color: #663399; }
.tn >>> .tn-dd .dropdown-toggle::after { display: none; }
.tn >>> .tn-dd .btn:focus { box-shadow: none; }
.tn-badge {
  position: absolute;
  top: -2px;
  right: -4px;
  min-width: 20px;
  height: 17px;
  padding: 0 5px;
  border: 2px solid #fff;
  border-radius: 999px;
  background: #ef4444;
  color: #fff;
  font-size: 11px;
  font-weight: 700;
  line-height: 14px;
  text-align: center;
}

/* ---- language ---- */
.tn >>> .tn-lang-btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  height: 38px;
  padding: 0 8px;
  border-radius: 10px;
  color: #47404f;
  text-decoration: none;
}
.tn >>> .tn-lang-btn:hover,
.tn >>> .show > .tn-lang-btn { background: #f3f0f8; }
.tn-flag {
  display: inline-flex;
  width: 22px;
  height: 15px;
  border-radius: 3px;
  overflow: hidden;
  box-shadow: 0 0 0 1px rgba(0, 0, 0, .08);
  flex: 0 0 auto;
}
.tn-flag >>> svg { width: 100%; height: 100%; display: block; }
.tn-lang-code { font-weight: 700; font-size: 13px; letter-spacing: .04em; color: #52495a; }

/* ---- notifications ---- */
.tn >>> .tn-notif-menu { min-width: 280px; padding: 6px; border-radius: 12px; }
.tn-notif {
  display: flex !important;
  align-items: center;
  gap: 10px;
  padding: 8px 10px !important;
  border-radius: 8px;
  white-space: normal;
}
.tn-notif-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 34px;
  height: 34px;
  border-radius: 8px;
  background: #fef3c7;
  color: #b45309;
  flex: 0 0 auto;
}
.tn-notif-icon .tn-svg { width: 18px; height: 18px; }
.tn >>> .tn-notif-empty { color: #8a8494; font-size: 13px; }

/* ---- user ---- */
.tn >>> .tn-user-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 40px;
  padding: 0 6px 0 3px;
  border-radius: 999px;
  color: #1f1f2e;
  text-decoration: none;
}
.tn >>> .tn-user-btn:hover,
.tn >>> .show > .tn-user-btn { background: #f3f0f8; }
.tn-avatar {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: linear-gradient(135deg, #8e5cc9, #663399);
  color: #fff;
  font-size: 14px;
  font-weight: 700;
  box-shadow: 0 0 0 2px #ede7f6;
}
.tn-user-name { font-size: 15px; font-weight: 500; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.tn-caret { width: 14px; height: 14px; color: #8a8494; }
.tn >>> .tn-user { margin: 0 !important; }
.tn >>> .tn-dd .dropdown-menu { border-radius: 12px; border-color: #ececf2; box-shadow: 0 12px 32px rgba(31, 31, 46, .12); }

/* ---- language menu ---- */
.lang-menu {
  min-width: 190px;
  padding: 6px;
}
.lang-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 10px;
  border-radius: 8px;
  color: #47404f;
  font-size: 14px;
  text-decoration: none;
  transition: background-color 0.15s ease;
}
.lang-item + .lang-item {
  margin-top: 2px;
}
.lang-item:hover {
  background: #f3f0f8;
  color: #47404f;
}
.lang-item.active {
  background: #ede7f6;
  color: #663399;
  font-weight: 600;
}
.lang-name {
  flex: 1;
  white-space: nowrap;
}
.lang-check {
  font-size: 16px;
  color: #663399;
}

/* ---- small screens ---- */
@media (max-width: 767px) {
  .tn-user-name, .tn-caret, .tn-lang-code { display: none; }
  .tn-pos { padding: 0 12px; font-size: 14px; height: 36px; }
  .tn-right { gap: 2px; padding-right: 8px; }
}
@media (max-width: 575px) {
  .tn-hide-xs,
  .tn >>> .tn-hide-xs { display: none; }
  .tn-pos span { display: none; }
  .tn-pos { width: 36px; padding: 0; justify-content: center; }
}
</style>
