<template>
  <div>
    <div class="customizer" :class="{ open: isOpen }">
      <div class="handle" @click="isOpen = !isOpen">
        <i class="i-Gear spin"></i>
      </div>

      <vue-perfect-scrollbar
        :settings="{ suppressScrollX: true, wheelPropagation: false }"
        class="customizer-body ps rtl-ps-none"
      >
        <div
          class
          v-if="getThemeMode.layout != 'vertical-sidebar' && getThemeMode.layout != 'vertical-sidebar-two'"
        >
          <div class="card-header" id="headingOne">
            <p class="mb-0">RTL</p>
          </div>

          <div class="card-body">
            <label class="checkbox checkbox-primary">
              <input type="checkbox" id="rtl-checkbox" @change="changeThemeRtl" />
              <span>Enable RTL</span>
              <span class="checkmark"></span>
            </label>
          </div>
        </div>

        <div class>
          <div class="card-header">
            <p class="mb-0">Dark Mode</p>
          </div>

          <div class="card-body">
            <label class="switch switch-primary mr-3 mt-2" v-b-popover.hover.left="'Dark Mode'">
              <input type="checkbox" @click="changeThemeMode" />
              <span class="slider"></span>
            </label>
          </div>
        </div>

         <div class>
          <div class="card-header">
            <p class="mb-0">Language</p>
          </div>

          <div class="card-body">
             <div class="menu-icon-language">
              <a @click="SetLocal('en')">
                <i title="en" class="flag-icon flag-icon-squared flag-icon-gb"></i> English
              </a>
              <a @click="SetLocal('fr')">
                <i title="fr" class="flag-icon flag-icon-squared flag-icon-fr"></i>
                <span class="title-lang">French</span>
              </a>
              <a @click="SetLocal('ar')">
                <i title="sa" class="flag-icon flag-icon-squared flag-icon-ma"></i>
                <span class="title-lang">Arabic</span>
              </a>
            </div>
          </div>
        </div>
      </vue-perfect-scrollbar>
    </div>
  </div>
</template>

<script>
import { mapGetters, mapActions } from "vuex";

export default {
  data() {
    return {
      isOpen: false,
       langs: [
        "en",
        "fr",
        "ar"
      ],
      
    };
  },

  computed: {
    ...mapGetters(["getThemeMode", "getcompactLeftSideBarBgColor"]),
  },

  methods: {
    ...mapActions([
      "changeThemeRtl",
      "changeThemeLayout",
      "changeThemeMode",
      "changecompactLeftSideBarBgColor",
    ]),

    SetLocal(locale) {
      this.$i18n.locale = locale;
      this.$store.dispatch("language/setLanguage", locale);
      Fire.$emit("ChangeLanguage");
    },
   
  },
};
</script>

<style lang="scss" scoped>
</style>
