<template>
  <div>
    <transition name="ds-fade">
      <div v-if="open" class="ds-overlay" @click="$emit('close')"></div>
    </transition>
    <transition :name="isRtl ? 'ds-slide-left' : 'ds-slide-right'">
      <aside
        v-if="open"
        class="ds-panel"
        :class="{ 'is-rtl': isRtl }"
        :dir="dir"
        role="dialog"
        aria-modal="true"
        :aria-label="title"
        @keydown.esc="$emit('close')"
        tabindex="-1"
        ref="panel"
      >
        <!-- header -->
        <header class="ds-head">
          <div class="ds-head-text">
            <div class="ds-kicker">{{ $t('DaySummary.kicker') }}</div>
            <h2>{{ title }}</h2>
            <div class="ds-filters">
              <input type="date" v-model="date" :max="today" class="ds-input" @change="load" />
              <select v-if="warehouses.length > 1" v-model="warehouseId" class="ds-input" @change="load">
                <option value="">{{ $t('DaySummary.all_warehouses') }}</option>
                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
              </select>
            </div>
          </div>
          <div class="ds-head-actions">
            <button type="button" class="ds-icon-btn" :title="$t('DaySummary.refresh')" :disabled="loading" @click="load">
              <svg viewBox="0 0 24 24" :class="{ 'ds-spin': loading }"><path d="M21 12a9 9 0 0 1-15.5 6.2M3 12A9 9 0 0 1 18.5 5.8" /><path d="M21 4v5h-5M3 20v-5h5" /></svg>
            </button>
            <button type="button" class="ds-icon-btn" :title="$t('DaySummary.close')" @click="$emit('close')">
              <svg viewBox="0 0 24 24"><path d="M18 6L6 18M6 6l12 12" /></svg>
            </button>
          </div>
        </header>

        <div class="ds-body">
          <div v-if="error" class="ds-error">{{ error }}</div>
          <div v-else-if="!data" class="ds-loading"><div class="spinner sm spinner-primary"></div></div>

          <template v-else>
            <!-- hero -->
            <section class="ds-hero">
              <div class="ds-hero-top">
                <div>
                  <div class="ds-hero-label">{{ $t('DaySummary.net_sales') }}</div>
                  <div class="ds-hero-value"><bdi>{{ money(data.sales.net) }}</bdi></div>
                </div>
                <div class="ds-pills">
                  <span class="ds-pill">{{ $t('DaySummary.n_sales', { n: data.sales.count }) }}</span>
                  <span class="ds-pill">{{ $t('DaySummary.n_items', { n: qty(data.sales.items) }) }}</span>
                </div>
              </div>
              <div class="ds-flow">
                <template v-if="inShare !== null">
                  <span class="ds-flow-in" :style="{ width: inShare + '%' }"></span>
                  <span class="ds-flow-out" :style="{ width: (100 - inShare) + '%' }"></span>
                </template>
              </div>
              <div class="ds-flow-legend">
                <span><i class="ds-dot is-in"></i>{{ $t('DaySummary.money_in') }} <bdi>{{ money(data.payments.received_total) }}</bdi></span>
                <span><i class="ds-dot is-out"></i>{{ $t('DaySummary.money_out') }} <bdi>{{ money(data.payments.given_total) }}</bdi></span>
              </div>
            </section>

            <!-- sales / purchases -->
            <div class="ds-grid-2">
              <section class="ds-card is-sales">
                <h3>
                  <svg viewBox="0 0 24 24"><circle cx="9" cy="21" r="1" /><circle cx="20" cy="21" r="1" /><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6" /></svg>
                  {{ $t('DaySummary.sales') }}
                </h3>
                <div class="ds-row"><span>{{ $t('DaySummary.gross') }}</span><bdi>{{ money(data.sales.gross) }}</bdi></div>
                <div class="ds-row"><span>{{ $t('DaySummary.discount') }}</span><bdi>{{ money(data.sales.discount) }}</bdi></div>
                <div v-if="data.sales.shipping" class="ds-row"><span>{{ $t('DaySummary.shipping') }}</span><bdi>{{ money(data.sales.shipping) }}</bdi></div>
                <div class="ds-row is-total"><span>{{ $t('DaySummary.net_sales') }}</span><bdi>{{ money(data.sales.net) }}</bdi></div>
                <div class="ds-row is-muted"><span>{{ $t('DaySummary.returns') }}</span><bdi>{{ money(data.sales.returns) }}</bdi></div>
                <div class="ds-row is-muted"><span>{{ $t('DaySummary.average_sale') }}</span><bdi>{{ money(data.sales.average) }}</bdi></div>
              </section>

              <section class="ds-card is-purchases">
                <h3>
                  <svg viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" /><path d="M3 6h18M16 10a4 4 0 0 1-8 0" /></svg>
                  {{ $t('DaySummary.purchases') }}
                </h3>
                <div class="ds-row"><span>{{ $t('DaySummary.gross') }}</span><bdi>{{ money(data.purchases.gross) }}</bdi></div>
                <div class="ds-row"><span>{{ $t('DaySummary.discount') }}</span><bdi>{{ money(data.purchases.discount) }}</bdi></div>
                <div v-if="data.purchases.shipping" class="ds-row"><span>{{ $t('DaySummary.shipping') }}</span><bdi>{{ money(data.purchases.shipping) }}</bdi></div>
                <div class="ds-row is-total"><span>{{ $t('DaySummary.net_purchases') }}</span><bdi>{{ money(data.purchases.net) }}</bdi></div>
                <div class="ds-row is-muted"><span>{{ $t('DaySummary.returns') }}</span><bdi>{{ money(data.purchases.returns) }}</bdi></div>
                <div class="ds-row is-muted"><span>{{ $t('DaySummary.average_purchase') }}</span><bdi>{{ money(data.purchases.average) }}</bdi></div>
              </section>
            </div>

            <!-- profit: administrators only -->
            <section v-if="data.profit" class="ds-card is-profit">
              <h3>
                <svg viewBox="0 0 24 24"><path d="M23 6l-9.5 9.5-5-5L1 18" /><path d="M17 6h6v6" /></svg>
                {{ $t('DaySummary.profit') }}
              </h3>
              <div class="ds-row"><span>{{ $t('DaySummary.cogs') }}</span><bdi>{{ money(data.profit.cogs) }}</bdi></div>

              <div class="ds-profit-line" :class="{ 'is-loss': data.profit.gross_profit < 0 }">
                <div class="ds-row"><span>{{ $t('DaySummary.gross_profit') }}</span><bdi>{{ money(data.profit.gross_profit) }}</bdi></div>
                <div class="ds-bar"><span :style="{ width: barWidth(data.profit.gross_profit) + '%' }"></span></div>
                <small>{{ $t('DaySummary.pct_of_sales', { n: pct(data.profit.gross_profit) }) }}</small>
              </div>
              <div class="ds-profit-line" :class="{ 'is-loss': data.profit.net_profit < 0 }">
                <div class="ds-row"><span>{{ $t('DaySummary.net_profit') }}</span><bdi>{{ money(data.profit.net_profit) }}</bdi></div>
                <div class="ds-bar"><span :style="{ width: barWidth(data.profit.net_profit) + '%' }"></span></div>
                <small>{{ $t('DaySummary.pct_of_sales', { n: pct(data.profit.net_profit) }) }}</small>
              </div>

              <div class="ds-foot">
                <span>{{ $t('DaySummary.discount') }} <b><bdi>{{ money(data.profit.discount) }}</bdi></b></span>
                <span>{{ $t('DaySummary.expenses') }} <b><bdi>{{ money(data.profit.expenses) }}</bdi></b></span>
              </div>
            </section>

            <!-- payments by method -->
            <section class="ds-card is-payments">
              <h3>
                <svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2" /><path d="M16 12h2" /><path d="M2 9h20" /></svg>
                {{ $t('DaySummary.payments_by_method') }}
              </h3>
              <div v-if="!data.payments.methods.length" class="ds-empty">{{ $t('DaySummary.no_payments') }}</div>
              <div v-else class="ds-table-wrap">
                <table class="ds-table">
                  <thead>
                    <tr>
                      <th></th>
                      <th v-for="m in data.payments.methods" :key="m" class="ds-num">{{ methodLabel(m) }}</th>
                      <th class="ds-num">{{ $t('DaySummary.total') }}</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><i class="ds-dot is-in"></i>{{ $t('DaySummary.received') }}</td>
                      <td v-for="m in data.payments.methods" :key="'r' + m" class="ds-num"><bdi>{{ money(data.payments.received[m] || 0) }}</bdi></td>
                      <td class="ds-num"><b><bdi>{{ money(data.payments.received_total) }}</bdi></b></td>
                    </tr>
                    <tr>
                      <td><i class="ds-dot is-out"></i>{{ $t('DaySummary.given') }}</td>
                      <td v-for="m in data.payments.methods" :key="'g' + m" class="ds-num"><bdi>{{ money(data.payments.given[m] || 0) }}</bdi></td>
                      <td class="ds-num"><b><bdi>{{ money(data.payments.given_total) }}</bdi></b></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>

            <!-- stock -->
            <section class="ds-card is-stock">
              <h3>
                <svg viewBox="0 0 24 24"><path d="M21 8v13H3V8" /><path d="M1 3h22v5H1z" /><path d="M10 12h4" /></svg>
                {{ $t('DaySummary.stock') }}
              </h3>
              <div v-if="data.stock.at_cost !== null" class="ds-row"><span>{{ $t('DaySummary.on_hand_cost') }}</span><bdi>{{ money(data.stock.at_cost) }}</bdi></div>
              <div class="ds-row"><span>{{ $t('DaySummary.on_hand_retail') }}</span><bdi>{{ money(data.stock.at_retail) }}</bdi></div>
              <div v-if="data.stock.potential_margin !== null" class="ds-row"><span>{{ $t('DaySummary.potential_margin') }}</span><bdi>{{ money(data.stock.potential_margin) }}</bdi></div>
              <div class="ds-row is-muted"><span>{{ $t('DaySummary.units_in_stock') }}</span><bdi>{{ number(data.stock.units) }}</bdi></div>
              <div class="ds-row is-muted"><span>{{ $t('DaySummary.out_of_stock') }}</span><bdi>{{ number(data.stock.out_of_stock) }}</bdi></div>
            </section>

            <!-- tiles -->
            <div class="ds-tiles">
              <div class="ds-tile">
                <span class="ds-tile-icon is-purple"><svg viewBox="0 0 24 24"><circle cx="9" cy="7" r="4" /><path d="M2 21v-1a6 6 0 0 1 10-4.5M19 14v6M16 17h6" /></svg></span>
                <div><strong>{{ data.new_customers }}</strong><small>{{ $t('DaySummary.new_customers') }}</small></div>
              </div>
              <div class="ds-tile">
                <span class="ds-tile-icon is-amber"><svg viewBox="0 0 24 24"><path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z" /><path d="M12 9v4M12 17h.01" /></svg></span>
                <div><strong>{{ number(data.stock.low_stock) }}</strong><small>{{ $t('DaySummary.low_stock') }}</small></div>
              </div>
              <div class="ds-tile">
                <span class="ds-tile-icon is-teal"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="4" /><path d="M1 21v-1a7 7 0 0 1 14 0v1M17 4a4 4 0 0 1 0 8M23 21v-1a7 7 0 0 0-4-6.3" /></svg></span>
                <div><strong>{{ data.on_shift }}</strong><small>{{ $t('DaySummary.on_shift') }}</small></div>
              </div>
            </div>
          </template>
        </div>
      </aside>
    </transition>
  </div>
</template>

<script>
import { mapGetters } from "vuex";

const todayIso = () => {
  const d = new Date();
  return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
};

export default {
  name: "DaySummary",
  props: {
    open: { type: Boolean, default: false }
  },
  data() {
    return {
      today: todayIso(),
      date: todayIso(),
      warehouseId: "",
      warehouses: [],
      data: null,
      loading: false,
      error: ""
    };
  },
  computed: {
    ...mapGetters(["currentUser"]),
    isRtl() {
      return this.$i18n.locale === "ar";
    },
    dir() {
      return this.isRtl ? "rtl" : "ltr";
    },
    title() {
      // the date is isolated (LRI ... PDI) so "2026-11-07" is not reordered inside Arabic text
      return this.date === this.today
        ? this.$t("DaySummary.title_today")
        : this.$t("DaySummary.title_day", { date: "\u2066" + this.date + "\u2069" });
    },
    inShare() {
      if (!this.data) return 50;
      const a = this.data.payments.received_total;
      const b = this.data.payments.given_total;
      return a + b > 0 ? Math.round((a / (a + b)) * 100) : null;
    }
  },
  // rendered at <body> level so the fixed header (its own stacking layer) cannot cover it
  mounted() {
    document.body.appendChild(this.$el);
  },
  beforeDestroy() {
    if (this.$el && this.$el.parentNode) this.$el.parentNode.removeChild(this.$el);
  },
  watch: {
    open(value) {
      if (value) {
        this.today = todayIso();
        this.load();
        this.$nextTick(() => this.$refs.panel && this.$refs.panel.focus());
      }
    }
  },
  methods: {
    load() {
      this.loading = true;
      this.error = "";
      axios
        .get("day_summary", { params: { date: this.date, warehouse_id: this.warehouseId || undefined } })
        .then(({ data }) => {
          this.data = data;
          this.warehouses = data.warehouses || [];
        })
        .catch(error => {
          // the app's axios interceptor rejects with the response body
          const body = error && error.response ? error.response.data : error;
          this.error = (body && body.message) || this.$t("DaySummary.error");
        })
        .finally(() => {
          this.loading = false;
        });
    },
    money(value) {
      const n = parseFloat(value) || 0;
      const s = n.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      const cur = this.currentUser && this.currentUser.currency ? this.currentUser.currency : "";
      return cur ? cur + " " + s : s;
    },
    number(value) {
      return (parseFloat(value) || 0).toLocaleString("en-US", { maximumFractionDigits: 2 });
    },
    qty(value) {
      const n = parseFloat(value) || 0;
      return Number.isInteger(n) ? n : n.toFixed(2);
    },
    pct(value) {
      const base = this.data ? this.data.sales.gross : 0;
      return base > 0 ? Math.round((value / base) * 100) : 0;
    },
    barWidth(value) {
      return Math.max(0, Math.min(100, Math.abs(this.pct(value))));
    },
    methodLabel(method) {
      const map = { Cash: "method_cash", tpe: "method_tpe", cheque: "method_cheque", "bank transfer": "method_bank_transfer", other: "method_other" };
      return map[method] ? this.$t("PosNext." + map[method]) : method;
    }
  }
};
</script>

<style scoped>
.ds-overlay {
  position: fixed;
  inset: 0;
  background: rgba(17, 17, 27, .38);
  z-index: 10040; /* above the theme customizer button (9999) */
}
.ds-panel {
  --ds-primary: #663399;
  --ds-border: #ececf2;
  --ds-muted: #7a7590;
  --ds-in: #22a06b;
  --ds-out: #e5484d;
  position: fixed;
  top: 0;
  right: 0;
  bottom: 0;
  width: 640px;
  max-width: 100vw;
  display: flex;
  flex-direction: column;
  background: #fff;
  box-shadow: -12px 0 40px rgba(17, 17, 27, .18);
  z-index: 10041;
  outline: 0;
  text-align: start;
  color: #1f1f2e;
}
.ds-panel.is-rtl { right: auto; left: 0; box-shadow: 12px 0 40px rgba(17, 17, 27, .18); }
.ds-panel svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex: 0 0 auto; }

.ds-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; padding: 22px 24px 14px; border-bottom: 1px solid var(--ds-border); }
.ds-kicker { color: var(--ds-primary); font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
.ds-head h2 { margin: 2px 0 8px; font-size: 22px; font-weight: 800; }
.ds-filters { display: flex; gap: 8px; flex-wrap: wrap; }
.ds-input { height: 32px; padding: 0 8px; border: 1px solid var(--ds-border); border-radius: 8px; background: #fff; color: #47404f; font-size: 13px; }
.ds-input:focus { outline: 0; border-color: var(--ds-primary); box-shadow: 0 0 0 3px #f3edfa; }
.ds-head-actions { display: flex; gap: 4px; }
.ds-icon-btn { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border: 0; border-radius: 10px; background: transparent; color: #47404f; cursor: pointer; }
.ds-icon-btn:hover:not(:disabled) { background: #f3f0f8; color: var(--ds-primary); }
.ds-spin { animation: ds-spin .8s linear infinite; }
@keyframes ds-spin { to { transform: rotate(360deg); } }

.ds-body { flex: 1; min-height: 0; overflow-y: auto; padding: 18px 24px 24px; display: flex; flex-direction: column; gap: 14px; background: #fbfbfd; }
.ds-body > * { flex-shrink: 0; } /* cards keep their height, the body scrolls */
.ds-loading, .ds-empty { display: flex; justify-content: center; padding: 30px 0; color: var(--ds-muted); }
.ds-error { padding: 14px; border-radius: 10px; background: #fef2f2; color: #b91c1c; }

.ds-hero { padding: 18px 20px; border: 1px solid #e4dbf3; border-radius: 16px; background: linear-gradient(135deg, #f7f3fd, #fff); }
.ds-hero-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 10px; }
.ds-hero-label { color: var(--ds-muted); font-size: 14px; }
.ds-hero-value { font-size: 32px; font-weight: 800; color: #3b2160; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; letter-spacing: -.02em; }
.ds-pills { display: flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end; }
.ds-pill { padding: 4px 12px; border: 1px solid #d9c9f0; border-radius: 999px; background: #fff; color: var(--ds-primary); font-size: 13px; font-weight: 600; white-space: nowrap; }
.ds-flow { display: flex; height: 8px; margin: 14px 0 8px; border-radius: 999px; overflow: hidden; background: #eee; }
.ds-flow-in { background: var(--ds-in); }
.ds-flow-out { background: var(--ds-out); }
.ds-flow-legend { display: flex; justify-content: space-between; gap: 10px; color: var(--ds-muted); font-size: 13px; }
.ds-dot { display: inline-block; width: 8px; height: 8px; margin-inline-end: 6px; border-radius: 50%; vertical-align: middle; }
.ds-dot.is-in { background: var(--ds-in); }
.ds-dot.is-out { background: var(--ds-out); }

.ds-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
.ds-card { position: relative; padding: 14px 18px; border: 1px solid var(--ds-border); border-radius: 14px; background: #fff; overflow: hidden; }
.ds-card::before { content: ""; position: absolute; top: 0; bottom: 0; inset-inline-start: 0; width: 4px; background: var(--ds-accent, var(--ds-primary)); }
.ds-card.is-sales { --ds-accent: #7c3aed; }
.ds-card.is-purchases { --ds-accent: #3b82f6; }
.ds-card.is-profit { --ds-accent: #22a06b; }
.ds-card.is-payments { --ds-accent: #f59e0b; }
.ds-card.is-stock { --ds-accent: #14b8a6; }
.ds-card h3 { display: flex; align-items: center; gap: 8px; margin: 0 0 6px; padding-bottom: 10px; border-bottom: 1px solid var(--ds-border); color: #5b5670; font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
.ds-card h3 svg { width: 18px; height: 18px; }
.ds-row { display: flex; justify-content: space-between; align-items: baseline; gap: 10px; padding: 6px 0; font-size: 14px; }
.ds-row bdi { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; white-space: nowrap; }
.ds-row.is-total { margin-top: 4px; padding-top: 9px; border-top: 1px dashed #d7d4e2; font-weight: 800; }
.ds-row.is-muted { color: var(--ds-muted); }

.ds-profit-line { margin-top: 4px; color: #1f7a54; }
.ds-profit-line .ds-row { font-weight: 700; font-size: 15px; }
.ds-profit-line small { color: var(--ds-muted); font-size: 12px; }
.ds-bar { height: 7px; border-radius: 999px; background: #e8f5ef; overflow: hidden; }
.ds-bar span { display: block; height: 100%; border-radius: 999px; background: var(--ds-in); }
.ds-profit-line.is-loss { color: #b91c1c; }
.ds-profit-line.is-loss .ds-bar { background: #fde8e8; }
.ds-profit-line.is-loss .ds-bar span { background: var(--ds-out); }
.ds-foot { display: flex; flex-wrap: wrap; gap: 6px 22px; margin-top: 12px; padding-top: 10px; border-top: 1px solid var(--ds-border); color: var(--ds-muted); font-size: 13px; }
.ds-foot b { color: #1f1f2e; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

.ds-table-wrap { overflow-x: auto; }
.ds-table { width: 100%; border-collapse: collapse; font-size: 14px; }
.ds-table th { padding: 8px 6px; border-bottom: 1px solid var(--ds-border); color: var(--ds-muted); font-size: 11px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; text-align: start; white-space: nowrap; }
.ds-table td { padding: 9px 6px; border-bottom: 1px solid var(--ds-border); white-space: nowrap; }
.ds-table tr:last-child td { border-bottom: 0; }
.ds-num { text-align: end !important; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }

.ds-tiles { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.ds-tile { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border: 1px solid var(--ds-border); border-radius: 14px; background: #fff; }
.ds-tile strong { display: block; font-size: 22px; line-height: 1.1; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.ds-tile small { color: var(--ds-muted); font-size: 13px; }
.ds-tile-icon { display: inline-flex; align-items: center; justify-content: center; width: 42px; height: 42px; border-radius: 10px; }
.ds-tile-icon.is-purple { background: #f3edfa; color: #7c3aed; }
.ds-tile-icon.is-amber { background: #fef3c7; color: #b45309; }
.ds-tile-icon.is-teal { background: #ddf6f2; color: #0d9488; }

.ds-fade-enter-active, .ds-fade-leave-active { transition: opacity .2s; }
.ds-fade-enter, .ds-fade-leave-to { opacity: 0; }
.ds-slide-right-enter-active, .ds-slide-right-leave-active,
.ds-slide-left-enter-active, .ds-slide-left-leave-active { transition: transform .25s ease; }
.ds-slide-right-enter, .ds-slide-right-leave-to { transform: translateX(100%); }
.ds-slide-left-enter, .ds-slide-left-leave-to { transform: translateX(-100%); }

@media (max-width: 640px) {
  .ds-head { padding: 16px 16px 12px; }
  .ds-body { padding: 14px 16px 20px; }
  .ds-grid-2, .ds-tiles { grid-template-columns: 1fr; }
  .ds-hero-value { font-size: 26px; }
  .ds-hero-top { flex-direction: column; }
}
</style>
