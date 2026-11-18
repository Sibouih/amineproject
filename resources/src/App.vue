<template>
  <div v-if="Loading">
    <router-view></router-view>

    <customizer></customizer>

    <!-- a new version was uploaded while this tab was open -->
    <div v-if="updateAvailable" class="app-update" :dir="$i18n.locale === 'ar' ? 'rtl' : 'ltr'" role="status">
      <span>{{ $t('AppUpdate.text') }}</span>
      <button type="button" class="app-update-btn" @click="reloadApp">{{ $t('AppUpdate.reload') }}</button>
      <button type="button" class="app-update-close" :aria-label="$t('AppUpdate.later')" @click="updateAvailable = false">&times;</button>
    </div>
  </div>
</template>


<script>
import { mapActions, mapGetters } from "vuex";

export default {
  data() {
    return {
      Loading:false,
      // new front-end build on the server (see checkVersion)
      updateAvailable: false,
      versionTimer: null,
    };
  },
  computed: {
    
    ...mapGetters(["getThemeMode","isAuthenticated"]),
    themeName() {
      return this.getThemeMode.dark ? "dark-theme" : " ";
    },
    rtl() {
      return this.getThemeMode.rtl ? "rtl" : " ";
    }
  },

  metaInfo() {
    return {
      // if no subcomponents specify a metaInfo.title, this title will be used
      title: "Dashboard",
      // all titles will be injected into this template
      titleTemplate: "%s | welcome",
      bodyAttrs: {
        class: [this.themeName, "text-left"]
      },
      htmlAttrs: {
        dir: this.rtl
      },
      
    };
  },
methods:{
    ...mapActions([
      "refreshUserPermissions",
    ]),

    // compares the build this tab loaded with the one on the server
    checkVersion() {
      const loaded = window.APP_BUILD;
      if (!loaded || this.updateAvailable) return;
      axios
        .get("app_version", { params: { t: Date.now() } })
        .then(({ data }) => {
          if (data && data.build && data.build !== loaded) this.updateAvailable = true;
        })
        .catch(() => {});
    },
    onVisible() {
      if (document.visibilityState === "visible") this.checkVersion();
    },
    reloadApp() {
      window.location.reload();
    },
},

  mounted() {
    // every 5 minutes, and each time the tab comes back to the front
    this.versionTimer = setInterval(() => this.checkVersion(), 5 * 60 * 1000);
    document.addEventListener("visibilitychange", this.onVisible);
  },

  beforeDestroy() {
    clearInterval(this.versionTimer);
    document.removeEventListener("visibilitychange", this.onVisible);
  },

 beforeMount() {
        this.refreshUserPermissions();
        setTimeout(() => this.Loading= true, 300);
   
  }

};
</script>

<style>
.app-update {
  position: fixed;
  bottom: 18px;
  left: 50%;
  transform: translateX(-50%);
  z-index: 20000;
  display: flex;
  align-items: center;
  gap: 12px;
  max-width: calc(100vw - 32px);
  padding: 10px 12px 10px 16px;
  border-radius: 12px;
  background: #3b2160;
  color: #fff;
  font-size: 14px;
  box-shadow: 0 10px 30px rgba(17, 17, 27, .3);
}
.app-update-btn {
  height: 32px;
  padding: 0 14px;
  border: 0;
  border-radius: 8px;
  background: #fff;
  color: #663399;
  font-weight: 700;
  cursor: pointer;
  white-space: nowrap;
}
.app-update-close {
  border: 0;
  background: transparent;
  color: #fff;
  opacity: .7;
  font-size: 20px;
  line-height: 1;
  cursor: pointer;
}
.app-update-close:hover { opacity: 1; }
</style>
