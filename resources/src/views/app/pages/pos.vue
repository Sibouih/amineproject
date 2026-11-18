<template>
  <div class="posx" :dir="dir" :class="{ 'posx-rtl': isRtl }">
    <!-- the user's role has no "Point of sale" permission -->
    <div v-if="noPosAccess" class="posx-denied">
      <div>
        <span class="posx-denied-icon"><svg-icon name="lock" /></span>
        <h4>{{ $t('PosNext.no_access_title') }}</h4>
        <p>{{ $t('PosNext.no_access_text') }}</p>
        <button v-if="posOnly" type="button" class="posx-pay" @click="logout">{{ $t('PosNext.logout') }}</button>
        <button v-else type="button" class="posx-pay" @click="$router.push('/app/dashboard')">
          <svg-icon name="home" /> {{ $t('PosNext.home') }}
        </button>
      </div>
    </div>
    <!-- ============================ TOP BAR ============================ -->
    <header class="posx-top">
      <div class="posx-brand">
        <img
          v-if="currentUser && currentUser.logo"
          :src="'/images/' + currentUser.logo"
          alt=""
          @error="$event.target.style.display = 'none'"
        />
        <span class="posx-brand-title">{{ $t('PosNext.title') }}</span>
      </div>

      <!-- Warehouse -->
      <b-dropdown
        variant="link"
        no-caret
        toggle-class="posx-pill posx-pill-warehouse"
        class="posx-dd"
        :class="{ 'is-locked': warehouseLocked }"
        :disabled="loadingInit || warehouseLocked"
      >
        <template #button-content>
          <span class="posx-pill-icon posx-pill-icon-solid"><svg-icon name="warehouse" /></span>
          <span class="posx-pill-text">
            <small>{{ $t('PosNext.warehouse') }}</small>
            <strong>{{ warehouseName || '—' }}</strong>
          </span>
          <svg-icon :name="warehouseLocked ? 'lock' : 'chevron-down'" class="posx-caret" />
        </template>
        <b-dropdown-item
          v-for="w in warehouses"
          :key="w.id"
          :active="w.id === warehouse_id"
          @click="selectWarehouse(w.id)"
        >{{ w.name }}</b-dropdown-item>
      </b-dropdown>

      <!-- Category -->
      <b-dropdown variant="link" no-caret toggle-class="posx-pill" class="posx-dd posx-hide-sm" menu-class="posx-dd-scroll">
        <template #button-content>
          <svg-icon name="folder" class="posx-pill-icon" />
          <span class="posx-pill-label">{{ categoryName || $t('PosNext.all_categories') }}</span>
          <svg-icon name="chevron-down" class="posx-caret" />
        </template>
        <b-dropdown-item :active="!category_id" @click="setCategory('')">{{ $t('PosNext.all_categories') }}</b-dropdown-item>
        <b-dropdown-item
          v-for="c in categories"
          :key="c.id"
          :active="c.id === category_id"
          @click="setCategory(c.id)"
        >{{ c.name }}</b-dropdown-item>
      </b-dropdown>

      <!-- Brand -->
      <b-dropdown variant="link" no-caret toggle-class="posx-pill" class="posx-dd posx-hide-sm" menu-class="posx-dd-scroll">
        <template #button-content>
          <svg-icon name="tag" class="posx-pill-icon" />
          <span class="posx-pill-label">{{ brandName || $t('PosNext.all_brands') }}</span>
          <svg-icon name="chevron-down" class="posx-caret" />
        </template>
        <b-dropdown-item :active="!brand_id" @click="setBrand('')">{{ $t('PosNext.all_brands') }}</b-dropdown-item>
        <b-dropdown-item
          v-for="b in brands"
          :key="b.id"
          :active="b.id === brand_id"
          @click="setBrand(b.id)"
        >{{ b.name }}</b-dropdown-item>
      </b-dropdown>

      <div class="posx-spacer"></div>

      <!-- Customer -->
      <div class="posx-customer">
        <span class="posx-avatar">{{ clientInitial }}</span>
        <div class="posx-customer-body">
          <small>{{ $t('PosNext.customer') }}</small>
          <!-- only one customer allowed for this user: shown, not selectable -->
          <strong v-if="clientLocked" class="posx-customer-fixed" :title="$t('PosNext.customer_locked')">
            <span>{{ clientName }}</span>
            <svg-icon name="lock" />
          </strong>
          <v-select
            v-else
            v-model="client_id"
            :options="clients"
            :reduce="c => c.id"
            label="name"
            :clearable="false"
            :dir="dir"
            :placeholder="$t('PosNext.search_customer')"
            class="posx-vselect"
          >
            <template #option="{ name }">
              <span class="posx-opt-avatar">{{ (name || '?').trim().charAt(0).toUpperCase() }}</span>
              <span class="posx-opt-name">{{ name }}</span>
            </template>
            <template #no-options>{{ $t('PosNext.no_records') }}</template>
          </v-select>
        </div>
      </div>

      <span class="posx-currency" :title="$t('PosNext.currency')">{{ currency || '—' }}</span>

      <button v-if="canAddClient && can('Customers_add')" type="button" class="posx-icon-btn" :title="$t('PosNext.add_customer')" @click="openAddCustomer">
        <svg-icon name="user-plus" />
      </button>

      <div class="posx-icon-group">
        <button type="button" class="posx-icon-btn" :title="$t('PosNext.recent_sales')" @click="openRecentSales">
          <svg-icon name="receipt" />
        </button>
        <button
          v-if="can('pos_settings') && !posOnly"
          type="button"
          class="posx-icon-btn posx-hide-sm"
          :title="$t('PosNext.settings')"
          @click="$router.push('/app/settings/pos_settings')"
        >
          <svg-icon name="settings" />
        </button>
        <b-dropdown variant="link" no-caret toggle-class="posx-icon-btn" class="posx-dd" right>
          <template #button-content>
            <svg-icon name="globe" />
          </template>
          <b-dropdown-item
            v-for="l in languageOptions"
            :key="l.code"
            :active="$i18n.locale === l.code"
            @click="setLocale(l.code)"
          >{{ l.name }}</b-dropdown-item>
        </b-dropdown>
        <button type="button" class="posx-icon-btn posx-hide-sm" :title="$t('PosNext.fullscreen')" @click="toggleFullScreen">
          <svg-icon name="maximize" />
        </button>
      </div>

      <b-dropdown variant="link" no-caret toggle-class="posx-user-btn" class="posx-dd" right>
        <template #button-content>
          <span class="posx-user">{{ userInitial }}</span>
        </template>
        <b-dropdown-text v-if="currentUser">
          <strong>{{ currentUser.username }}</strong>
        </b-dropdown-text>
        <b-dropdown-divider></b-dropdown-divider>
        <template v-if="!posOnly">
          <b-dropdown-item to="/app/dashboard">{{ $t('PosNext.dashboard') }}</b-dropdown-item>
          <b-dropdown-item to="/app/profile">{{ $t('PosNext.profile') }}</b-dropdown-item>
        </template>
        <b-dropdown-item @click="logout">{{ $t('PosNext.logout') }}</b-dropdown-item>
      </b-dropdown>
    </header>

    <!-- ============================ MAIN ============================ -->
    <main class="posx-main">
      <!-- -------- Cart -------- -->
      <section class="posx-cart">
        <div class="posx-cart-head">
          <span class="posx-cart-icon"><svg-icon name="cart" /></span>
          <div class="posx-cart-title">
            <strong>{{ $t('PosNext.current_cart') }}</strong>
            <small>
              {{ $t('PosNext.items_count', { n: itemsCount }) }}
              <span v-if="cartSince" class="posx-since" :class="{ 'is-old': cartFromEarlierDay }">{{ cartSince }}</span>
            </small>
          </div>
          <div class="posx-cart-total">
            <small>{{ $t('PosNext.total') }}</small>
            <strong><bdi>{{ money(GrandTotal) }}</bdi></strong>
          </div>
          <button
            type="button"
            class="posx-icon-btn posx-icon-btn-sm"
            :disabled="!details.length"
            :title="$t('PosNext.clear_cart')"
            @click="confirmReset"
          >
            <svg-icon name="trash" />
          </button>
        </div>

        <div class="posx-cart-lines">
          <div v-if="!details.length" class="posx-empty">
            <svg-icon name="cart" class="posx-empty-icon" />
            <p>{{ $t('PosNext.empty_title') }}</p>
            <small>{{ $t('PosNext.empty_hint') }}</small>
          </div>

          <div v-for="line in details" :key="line.detail_id" class="posx-line" :class="{ 'is-confirmed': line.confirmed }">
            <div class="posx-line-main">
              <div class="posx-line-name" :title="line.name">{{ line.name }}</div>
              <div class="posx-line-meta">
                <span v-if="line.added_at" class="posx-time" :title="lineTimesTitle(line)">
                  <svg-icon name="clock" /> {{ shortTime(line.added_at) }}
                </span>
                <span class="posx-mono">{{ line.code }}</span>
                <span v-if="priceChanged(line)" class="posx-old-price" :title="$t('PosNext.original_price')">· <s><bdi>{{ money(line.base_price) }}</bdi></s></span>
              </div>
              <input
                v-if="line.is_imei"
                v-model="line.imei_number"
                type="text"
                class="posx-imei"
                :disabled="lineLocked(line)"
                :placeholder="$t('PosNext.imei')"
              />
            </div>
            <!-- unit price, editable -->
            <label class="posx-price" :class="{ 'is-changed': priceChanged(line) }" :title="$t('PosNext.unit_price')">
              <input
                type="number"
                min="0"
                step="any"
                :value="round(linePrice(line))"
                :disabled="!canEditPrice || lineLocked(line)"
                :aria-label="$t('PosNext.unit_price')"
                @focus="$event.target.select()"
                @change="setPrice(line, $event.target.value)"
              />
            </label>
            <div class="posx-qty">
              <button type="button" :disabled="lineLocked(line)" @click="decrement(line)" :aria-label="'-'"><svg-icon name="minus" /></button>
              <input
                type="number"
                min="0"
                step="any"
                :value="line.quantity"
                :disabled="lineLocked(line)"
                @change="setQuantity(line, $event.target.value)"
              />
              <button type="button" :disabled="lineLocked(line)" @click="increment(line)" :aria-label="'+'"><svg-icon name="plus" /></button>
            </div>
            <div class="posx-line-total"><bdi>{{ money(line.subtotal) }}</bdi></div>
            <!-- confirm the product: afterwards it can never be changed or removed (until "Confirm sales") -->
            <button
              type="button"
              class="posx-line-confirm"
              :class="{ 'is-on': line.confirmed }"
              :disabled="line.confirmed"
              :title="line.confirmed ? lineTimesTitle(line) : $t('PosNext.confirm_line')"
              :aria-pressed="line.confirmed ? 'true' : 'false'"
              @click="toggleLineConfirmed(line)"
            >
              <svg-icon :name="line.confirmed ? 'lock' : 'check'" />
            </button>
            <button
              v-if="!lineLocked(line)"
              type="button"
              class="posx-line-remove"
              :title="$t('PosNext.remove')"
              @click="removeLine(line)"
            >
              <svg-icon name="x" />
            </button>
            <span v-else class="posx-line-remove-spacer"></span>
          </div>
        </div>

        <div class="posx-cart-foot">
          <div class="posx-foot-total">
            <span>{{ $t('PosNext.total') }}</span>
            <bdi>{{ money(GrandTotal) }}</bdi>
          </div>
        </div>
      </section>

      <!-- -------- Catalog -------- -->
      <section class="posx-catalog">
        <div class="posx-search-row">
          <div class="posx-search">
            <svg-icon name="scan" class="posx-search-icon" />
            <input
              ref="search"
              v-model="search"
              type="text"
              :placeholder="$t('PosNext.search_placeholder')"
              @input="onSearchInput"
              @keydown.enter.prevent="onSearchEnter"
            />
            <button v-if="search" type="button" class="posx-search-clear" @click="clearSearch"><svg-icon name="x" /></button>
          </div>
          <button type="button" class="posx-btn" @click="openScanner">
            <svg-icon name="scan" /> <span>{{ $t('PosNext.scan') }}</span>
          </button>
          <button type="button" class="posx-btn" @click="openProductInfo()">
            <svg-icon name="info" /> <span>{{ $t('PosNext.product_info') }}</span>
          </button>
        </div>

        <div class="posx-grid-wrap">
          <div v-if="loadingProducts" class="posx-grid-loading"><div class="spinner sm spinner-primary"></div></div>
          <div v-if="!loadingProducts && !products.length" class="posx-empty">
            <svg-icon name="search" class="posx-empty-icon" />
            <p>{{ $t('PosNext.no_products') }}</p>
          </div>
          <div class="posx-grid">
            <button
              v-for="p in products"
              :key="p.id + '-' + (p.product_variant_id || 0)"
              type="button"
              class="posx-card"
              :class="{ 'is-out': isOut(p), 'is-busy': isPending(p) }"
              @click="addProduct(p)"
            >
              <div class="posx-card-img">
                <img
                  v-if="hasImage(p)"
                  :src="'/images/products/' + p.image"
                  alt=""
                  loading="lazy"
                  @error="$event.target.style.display = 'none'"
                />
                <svg-icon v-else name="box" class="posx-card-noimg" />
                <span v-if="inCart(p)" class="posx-card-incart">× {{ qty(inCart(p)) }}</span>
                <span
                  class="posx-card-info"
                  role="button"
                  tabindex="0"
                  :title="$t('PosNext.product_info')"
                  @click.stop="openProductInfo(p.id)"
                  @keydown.enter.stop.prevent="openProductInfo(p.id)"
                ><svg-icon name="info" /></span>
              </div>
              <div class="posx-card-name" :title="p.name">{{ p.name }}</div>
              <div class="posx-card-meta posx-mono">
                {{ p.code }}
                <template v-if="p.product_type !== 'is_service'">· <span :class="{ 'posx-stock-low': available(p) <= 0 }">{{ qty(available(p)) }}</span> {{ p.unitSale }}</template>
              </div>
              <div class="posx-card-foot">
                <bdi class="posx-card-price">{{ money(p.Net_price) }}</bdi>
                <span class="posx-card-add"><svg-icon name="plus" /></span>
              </div>
            </button>
          </div>
        </div>

        <div class="posx-pager">
          <button type="button" class="posx-icon-btn posx-icon-btn-sm" :disabled="page <= 1" @click="goPage(page - 1)">
            <svg-icon name="chevron-left" class="posx-flip" />
          </button>
          <div class="posx-pager-info">
            {{ $t('PosNext.page_n', { page: page }) }}
            <small>{{ $t('PosNext.products_count', { n: totalRows }) }}</small>
          </div>
          <div class="posx-pager-pages">
            <template v-for="(pg, i) in pageList">
              <span v-if="pg === '…'" :key="'e' + i" class="posx-pager-gap">…</span>
              <button
                v-else
                :key="'p' + pg"
                type="button"
                :class="{ active: pg === page }"
                @click="goPage(pg)"
              >{{ pg }}</button>
            </template>
          </div>
          <button type="button" class="posx-icon-btn posx-icon-btn-sm" :disabled="page >= lastPage" @click="goPage(page + 1)">
            <svg-icon name="chevron-right" class="posx-flip" />
          </button>
        </div>
      </section>
    </main>

    <!-- ============================ BOTTOM BAR ============================ -->
    <footer class="posx-bottom">
      <!-- connection + automatic saving of the cart -->
      <span class="posx-status" :class="statusClass" :title="cartSince">
        <i></i>{{ statusText }}
      </span>
      <button v-if="!posOnly" type="button" class="posx-btn" @click="$router.push('/app/dashboard')">
        <svg-icon name="home" /> <span>{{ $t('PosNext.home') }}</span>
      </button>
      <button type="button" class="posx-btn" @click="confirmReset">
        <svg-icon name="reset" /> <span>{{ $t('PosNext.reset') }}</span>
      </button>
      <div class="posx-spacer"></div>
      <div class="posx-payable">
        <small>{{ $t('PosNext.total_payable') }}</small>
        <strong><bdi>{{ money(GrandTotal) }}</bdi></strong>
      </div>
      <!-- one button: the whole cart (the day's products) becomes one sale -->
      <button type="button" class="posx-pay" :disabled="!details.length || busy" @click="openConfirm">
        <svg-icon name="check" /> {{ $t('PosNext.confirm_sales') }}
      </button>
    </footer>

    <!-- ============================ MODALS ============================ -->

    <!-- Confirm sales: payment + charges of the day -->
    <b-modal
      :content-class="modalClass"
      v-model="showConfirm"
      hide-footer
      centered
      scrollable
      :title="$t('PosNext.confirm_sales_title')"
      dialog-class="posx-dialog-lg posx-dialog-scroll"
    >
      <div :dir="dir" class="posx-pay-modal">
        <div class="posx-pay-due">
          <span>{{ $t('PosNext.amount_due') }}</span>
          <strong><bdi>{{ money(GrandTotal) }}</bdi></strong>
          <small>
            {{ clientName }} · {{ $t('PosNext.items_count', { n: itemsCount }) }}<template v-if="cartSince"> · {{ cartSince }}</template>
          </small>
        </div>

        <h6 class="posx-section-title">{{ $t('PosNext.payment_section') }}</h6>
        <div class="posx-form-grid posx-form-grid-3">
          <label>
            <span>{{ $t('PosNext.amount_paid') }}</span>
            <div class="posx-input">
              <em>{{ currency }}</em>
              <input type="number" min="0" step="any" v-model.number="payment.amount" />
            </div>
          </label>
          <label>
            <span>{{ $t('PosNext.method') }}</span>
            <select v-model="payment.Reglement" class="posx-select">
              <option v-for="m in paymentMethods" :key="m.value" :value="m.value">{{ m.label }}</option>
            </select>
          </label>
          <label>
            <span>{{ $t('PosNext.account') }}</span>
            <select v-model="payment.account_id" class="posx-select">
              <option :value="null">{{ $t('PosNext.none') }}</option>
              <option v-for="a in accounts" :key="a.id" :value="a.id">{{ a.account_name }}</option>
            </select>
          </label>
        </div>
        <div class="posx-quick">
          <button type="button" @click="payment.amount = GrandTotal">{{ $t('PosNext.paid_full') }}</button>
          <button type="button" @click="payment.amount = 0">{{ $t('PosNext.unpaid') }}</button>
          <span v-if="confirmDue > 0" class="posx-due-note">{{ $t('PosNext.due') }} <bdi>{{ money(confirmDue) }}</bdi></span>
        </div>

        <h6 class="posx-section-title">{{ $t('PosNext.charges_question') }}</h6>
        <div class="posx-seg" role="radiogroup">
          <button
            v-for="m in chargeModes"
            :key="m.value"
            type="button"
            role="radio"
            :aria-checked="chargeMode === m.value"
            :class="{ active: chargeMode === m.value }"
            @click="setChargeMode(m.value)"
          >{{ m.label }}</button>
        </div>
        <p v-if="chargeMode !== 'none' && !expenseCategories.length" class="posx-muted posx-hint">
          {{ $t('PosNext.no_expense_categories') }}
        </p>
        <div v-if="chargeMode !== 'none'" class="posx-charges">
          <div v-for="(c, i) in charges" :key="c.key" class="posx-charge-row" :class="{ 'is-single': chargeMode === 'total' }">
            <div class="posx-input">
              <em>{{ currency }}</em>
              <input type="number" min="0" step="any" v-model.number="c.amount" :placeholder="$t('PosNext.charge_amount')" :aria-label="$t('PosNext.charge_amount')" />
            </div>
            <select v-model="c.category_id" class="posx-select" :aria-label="$t('PosNext.charge_category')">
              <option :value="null" disabled>{{ $t('PosNext.charge_category') }}</option>
              <option v-for="cat in expenseCategories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
            <input
              type="text"
              v-model.trim="c.details"
              maxlength="150"
              class="posx-field"
              :placeholder="$t('PosNext.charge_description')"
              :aria-label="$t('PosNext.charge_description')"
            />
            <button
              v-if="chargeMode === 'lines'"
              type="button"
              class="posx-icon-btn posx-icon-btn-sm posx-danger"
              :disabled="charges.length < 2"
              :title="$t('PosNext.remove')"
              @click="removeChargeLine(i)"
            ><svg-icon name="x" /></button>
          </div>
          <button v-if="chargeMode === 'lines'" type="button" class="posx-link" @click="addChargeLine">
            <svg-icon name="plus" /> {{ $t('PosNext.add_charge_line') }}
          </button>
          <div class="posx-sum">
            <div><span>{{ $t('PosNext.charges_sum') }}</span><bdi>- {{ money(chargesTotal) }}</bdi></div>
            <div><span>{{ $t('PosNext.after_charges') }}</span><bdi>{{ money(GrandTotal - chargesTotal) }}</bdi></div>
          </div>
        </div>

        <label class="posx-block posx-mt">
          <span>{{ $t('PosNext.sale_note') }}</span>
          <textarea v-model="sale.notes" rows="2" class="posx-textarea"></textarea>
        </label>

        <div class="posx-modal-actions">
          <button type="button" class="posx-btn" @click="showConfirm = false">{{ $t('PosNext.cancel') }}</button>
          <button type="button" class="posx-pay" :disabled="busy" @click="submitConfirm">
            <span v-if="busy" class="spinner sm spinner-light"></span>
            <svg-icon v-else name="check" /> {{ $t('PosNext.confirm_sales') }}
          </button>
        </div>
      </div>
    </b-modal>

    <!-- Sale done -->
    <b-modal :content-class="modalClass" v-model="showDone" hide-footer centered :title="$t('PosNext.sale_done')" dialog-class="posx-dialog-sm">
      <div :dir="dir" class="posx-done" v-if="lastSale">
        <div class="posx-done-icon"><svg-icon name="check" /></div>
        <div class="posx-sum">
          <div><span>{{ $t('PosNext.total') }}</span><bdi>{{ money(lastSale.total) }}</bdi></div>
          <div><span>{{ $t('PosNext.paid') }}</span><bdi>{{ money(lastSale.paid) }}</bdi></div>
          <div v-if="lastSale.change > 0"><span>{{ $t('PosNext.change') }}</span><bdi>{{ money(lastSale.change) }}</bdi></div>
          <div v-if="lastSale.due > 0" class="posx-text-danger"><span>{{ $t('PosNext.due') }}</span><bdi>{{ money(lastSale.due) }}</bdi></div>
          <div v-if="lastSale.charges > 0"><span>{{ $t('PosNext.charges_sum') }}</span><bdi>- {{ money(lastSale.charges) }}</bdi></div>
        </div>
        <div class="posx-modal-actions posx-modal-actions-stack">
          <div class="posx-print-row">
            <button type="button" class="posx-btn" :disabled="printing" @click="printSale(lastSale.id, 'ticket')">
              <svg-icon name="receipt" /> {{ $t('PosNext.print_ticket') }}
            </button>
            <button type="button" class="posx-btn" :disabled="printing" @click="printSale(lastSale.id, 'a5')">
              <svg-icon name="printer" /> {{ $t('PosNext.print_a5') }}
            </button>
          </div>
          <button type="button" class="posx-btn" @click="downloadInvoice(lastSale.id)">
            <svg-icon name="file" /> {{ $t('PosNext.print_invoice') }}
          </button>
          <button v-if="can('Sales_view') && !posOnly" type="button" class="posx-btn" @click="openSale(lastSale.id)">
            <svg-icon name="eye" /> {{ $t('PosNext.view_sale') }}
          </button>
          <button type="button" class="posx-pay" @click="showDone = false">{{ $t('PosNext.new_sale') }}</button>
        </div>
      </div>
    </b-modal>


    <!-- Recent sales: products sold in the POS on a chosen day, printable -->
    <b-modal
      :content-class="modalClass"
      v-model="showRecent"
      hide-footer
      centered
      :title="$t('PosNext.recent_sales')"
      dialog-class="posx-dialog-lg posx-dialog-scroll"
    >
      <div :dir="dir" class="posx-day">
        <div class="posx-day-bar">
          <div class="posx-day-nav">
            <button type="button" class="posx-icon-btn posx-icon-btn-sm" :title="$t('PosNext.previous_day')" @click="shiftDay(-1)">
              <svg-icon name="chevron-left" class="posx-flip" />
            </button>
            <input type="date" v-model="dayDate" :max="todayIso()" class="posx-field posx-day-date" @change="loadDayProducts" />
            <button type="button" class="posx-icon-btn posx-icon-btn-sm" :title="$t('PosNext.next_day')" :disabled="dayDate >= todayIso()" @click="shiftDay(1)">
              <svg-icon name="chevron-right" class="posx-flip" />
            </button>
          </div>
          <div class="posx-day-print">
            <button type="button" class="posx-btn posx-btn-sm" :disabled="!dayData || !dayData.lines.length" @click="printDayList('ticket')">
              <svg-icon name="receipt" /> {{ $t('PosNext.print_ticket') }}
            </button>
            <button type="button" class="posx-btn posx-btn-sm posx-btn-primary" :disabled="!dayData || !dayData.lines.length" @click="printDayList('a4')">
              <svg-icon name="printer" /> {{ $t('PosNext.print_list') }}
            </button>
          </div>
        </div>

        <div v-if="loadingDay" class="posx-center"><div class="spinner sm spinner-primary"></div></div>
        <div v-else-if="!dayData || !dayData.lines.length" class="posx-empty posx-empty-sm"><p>{{ $t('PosNext.no_products_day') }}</p></div>
        <template v-else>
          <div class="posx-day-kpis">
            <div><small>{{ $t('PosNext.products') }}</small><strong>{{ dayData.totals.lines }}</strong></div>
            <div><small>{{ $t('PosNext.qty') }}</small><strong>{{ qty(dayData.totals.quantity) }}</strong></div>
            <div><small>{{ $t('PosNext.total') }}</small><strong><bdi>{{ money(dayData.totals.amount) }}</bdi></strong></div>
            <div v-if="dayData.totals.charges"><small>{{ $t('PosNext.charges_sum') }}</small><strong><bdi>- {{ money(dayData.totals.charges) }}</bdi></strong></div>
          </div>
          <div class="posx-table-wrap">
            <table class="posx-table">
              <thead>
                <tr>
                  <th>{{ $t('PosNext.time') }}</th>
                  <th>{{ $t('PosNext.item') }}</th>
                  <th class="posx-num">{{ $t('PosNext.qty') }}</th>
                  <th class="posx-num">{{ $t('PosNext.unit_price') }}</th>
                  <th class="posx-num">{{ $t('PosNext.total') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(l, i) in dayData.lines" :key="i">
                  <td class="posx-mono">{{ l.time }}</td>
                  <td>
                    <div class="posx-day-name">{{ l.name }}</div>
                    <small class="posx-muted posx-mono">{{ l.code }} · {{ l.Ref }}<template v-if="l.imei"> · IMEI {{ l.imei }}</template></small>
                  </td>
                  <td class="posx-num">{{ qty(l.quantity) }} {{ l.unit }}</td>
                  <td class="posx-num"><bdi>{{ money(l.price) }}</bdi></td>
                  <td class="posx-num"><bdi>{{ money(l.total) }}</bdi></td>
                </tr>
              </tbody>
            </table>
          </div>
          <div v-if="dayData.charges.length" class="posx-day-charges">
            <h6 class="posx-section-title">{{ $t('PosNext.charges_sum') }}</h6>
            <div v-for="c in dayData.charges" :key="c.Ref" class="posx-row-between">
              <span>{{ c.category }} <small class="posx-muted">{{ c.details }}</small></span>
              <bdi>- {{ money(c.amount) }}</bdi>
            </div>
          </div>
        </template>
      </div>
    </b-modal>

    <!-- Add customer -->
    <b-modal :content-class="modalClass" v-model="showAddCustomer" hide-footer centered :title="$t('PosNext.add_customer')" dialog-class="posx-dialog-md">
      <form :dir="dir" @submit.prevent="saveCustomer">
        <label class="posx-block">
          <span>{{ $t('PosNext.customer_name') }} *</span>
          <input ref="newClientName" v-model.trim="newClient.name" type="text" class="posx-field" required />
        </label>
        <div class="posx-form-grid">
          <label>
            <span>{{ $t('PosNext.phone') }}</span>
            <input v-model.trim="newClient.phone" type="text" class="posx-field" dir="ltr" />
          </label>
          <label>
            <span>{{ $t('PosNext.email') }}</span>
            <input v-model.trim="newClient.email" type="email" class="posx-field" dir="ltr" />
          </label>
          <label>
            <span>{{ $t('PosNext.city') }}</span>
            <input v-model.trim="newClient.city" type="text" class="posx-field" />
          </label>
          <label>
            <span>{{ $t('PosNext.address') }}</span>
            <input v-model.trim="newClient.adresse" type="text" class="posx-field" />
          </label>
        </div>
        <div class="posx-modal-actions">
          <button type="button" class="posx-btn" @click="showAddCustomer = false">{{ $t('PosNext.cancel') }}</button>
          <button type="submit" class="posx-pay" :disabled="busy || !newClient.name">{{ $t('PosNext.save') }}</button>
        </div>
      </form>
    </b-modal>

    <!-- Product info: search any product and see price, cost, stock per warehouse, history -->
    <b-modal
      :content-class="modalClass"
      v-model="showInfo"
      hide-footer
      centered
      scrollable
      :title="$t('PosNext.product_info')"
      dialog-class="posx-dialog-lg"
      @shown="focusInfoSearch"
    >
      <div :dir="dir" class="posx-info">
        <div class="posx-search posx-info-search">
          <svg-icon name="search" class="posx-search-icon" />
          <input
            ref="infoSearch"
            v-model="infoSearch"
            type="text"
            :placeholder="$t('PosNext.info_search_placeholder')"
            @input="onInfoSearch"
            @keydown.enter.prevent="onInfoEnter"
          />
          <button v-if="infoSearch" type="button" class="posx-search-clear" @click="clearInfoSearch"><svg-icon name="x" /></button>
        </div>

        <div v-if="infoLoading" class="posx-center"><div class="spinner sm spinner-primary"></div></div>

        <!-- results -->
        <template v-else-if="!info">
          <p v-if="!infoSearch.trim()" class="posx-muted posx-info-hint">{{ $t('PosNext.info_hint') }}</p>
          <div v-else-if="!infoResults.length" class="posx-empty posx-empty-sm"><p>{{ $t('PosNext.no_products') }}</p></div>
          <div v-else class="posx-info-results">
            <button v-for="r in infoResults" :key="r.id" type="button" class="posx-info-result" @click="loadProductInfo(r.id)">
              <span class="posx-info-thumb">
                <img v-if="hasImage(r)" :src="'/images/products/' + r.image" alt="" @error="$event.target.style.display = 'none'" />
                <svg-icon v-else name="box" />
              </span>
              <span class="posx-info-result-main">
                <strong>{{ r.name }}</strong>
                <small class="posx-mono">{{ r.code }}</small>
              </span>
              <span class="posx-info-result-side">
                <bdi class="posx-card-price">{{ money(r.price) }}</bdi>
                <small v-if="r.qte !== null" :class="{ 'posx-text-danger': r.qte <= 0 }">{{ $t('PosNext.stock') }} {{ qty(r.qte) }} {{ r.unit }}</small>
              </span>
            </button>
          </div>
        </template>

        <!-- detail -->
        <div v-else class="posx-info-detail">
          <button v-if="infoResults.length" type="button" class="posx-link" @click="info = null">
            <svg-icon name="chevron-left" class="posx-flip" /> {{ $t('PosNext.back') }}
          </button>

          <div class="posx-info-head">
            <span class="posx-info-img">
              <img v-if="hasImage(info)" :src="'/images/products/' + info.image" alt="" @error="$event.target.style.display = 'none'" />
              <svg-icon v-else name="box" />
            </span>
            <div class="posx-info-title">
              <h5>{{ info.name }}</h5>
              <div class="posx-mono posx-muted">{{ info.code }}</div>
              <div class="posx-info-tags">
                <span v-if="info.category">{{ info.category }}</span>
                <span v-if="info.brand">{{ info.brand }}</span>
                <span v-if="info.type === 'is_service'" class="is-accent">{{ $t('PosNext.service') }}</span>
                <span v-if="info.is_imei" class="is-accent">IMEI</span>
                <span v-if="info.not_selling" class="is-danger">{{ $t('PosNext.not_for_sale') }}</span>
              </div>
            </div>
          </div>

          <div class="posx-kpis">
            <div>
              <small>{{ $t('PosNext.price') }}</small>
              <strong><bdi>{{ money(infoPrice) }}</bdi></strong>
            </div>
            <div>
              <small>{{ $t('PosNext.cost') }}</small>
              <strong><bdi>{{ money(infoCost) }}</bdi></strong>
            </div>
            <div>
              <small>{{ $t('PosNext.margin') }}</small>
              <strong :class="infoPrice - infoCost < 0 ? 'posx-text-danger' : 'posx-text-success'">
                <bdi>{{ money(infoPrice - infoCost) }}</bdi>
                <em v-if="infoPrice > 0">{{ ((infoPrice - infoCost) / infoPrice * 100).toFixed(1) }}%</em>
              </strong>
            </div>
            <div v-if="info.total_qte !== null">
              <small>{{ $t('PosNext.total_stock') }}</small>
              <strong :class="{ 'posx-text-danger': info.total_qte <= info.stock_alert }">{{ qty(info.total_qte) }} {{ info.unit }}</strong>
            </div>
          </div>

          <h6 class="posx-info-section">{{ $t('PosNext.stock_by_warehouse') }}</h6>
          <div v-if="!info.stock.length" class="posx-muted">{{ $t('PosNext.no_records') }}</div>
          <div v-else class="posx-table-wrap">
            <table class="posx-table">
              <thead>
                <tr>
                  <th>{{ $t('PosNext.warehouse') }}</th>
                  <th v-if="info.type === 'is_variant'">{{ $t('PosNext.variant') }}</th>
                  <th class="posx-num">{{ $t('PosNext.qty') }}</th>
                  <th class="posx-num">{{ $t('PosNext.price') }}</th>
                  <th class="posx-num">{{ $t('PosNext.cost') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(s, i) in info.stock" :key="i" :class="{ 'is-current': s.warehouse_id === warehouse_id }">
                  <td>{{ s.warehouse }} <small v-if="s.warehouse_id === warehouse_id" class="posx-here">{{ $t('PosNext.this_pos') }}</small></td>
                  <td v-if="info.type === 'is_variant'">{{ s.variant }} <small class="posx-mono posx-muted">{{ s.code }}</small></td>
                  <td class="posx-num" :class="{ 'posx-text-danger': s.qte !== null && s.qte <= info.stock_alert }">
                    {{ s.qte === null ? '—' : qty(s.qte) + ' ' + info.unit }}
                  </td>
                  <td class="posx-num"><bdi>{{ money(s.price) }}</bdi></td>
                  <td class="posx-num"><bdi>{{ money(s.cost) }}</bdi></td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="posx-info-grid">
            <div><span>{{ $t('PosNext.unit_sale') }}</span><strong>{{ info.unit_sale || '—' }}</strong></div>
            <div><span>{{ $t('PosNext.unit_purchase') }}</span><strong>{{ info.unit_purchase || '—' }}</strong></div>
            <div>
              <span>{{ $t('PosNext.tax') }}</span>
              <strong>{{ info.tax }}% · {{ info.tax_method === '2' ? $t('PosNext.tax_inclusive') : $t('PosNext.tax_exclusive') }}</strong>
            </div>
            <div><span>{{ $t('PosNext.stock_alert') }}</span><strong>{{ qty(info.stock_alert) }}</strong></div>
            <div><span>{{ $t('PosNext.sold_30') }}</span><strong>{{ qty(info.sold_30_days) }}</strong></div>
            <div><span>{{ $t('PosNext.last_sale') }}</span><strong>{{ info.last_sale_date || '—' }}</strong></div>
            <div class="posx-info-wide">
              <span>{{ $t('PosNext.last_purchase') }}</span>
              <strong v-if="info.last_purchase">
                {{ info.last_purchase.date }} · {{ info.last_purchase.provider || '—' }} ·
                {{ qty(info.last_purchase.quantity) }} × <bdi>{{ money(info.last_purchase.cost) }}</bdi>
                <small class="posx-mono posx-muted">({{ info.last_purchase.Ref }})</small>
              </strong>
              <strong v-else>—</strong>
            </div>
            <div v-if="info.note" class="posx-info-wide"><span>{{ $t('PosNext.note') }}</span><strong>{{ info.note }}</strong></div>
          </div>

          <div class="posx-modal-actions">
            <button type="button" class="posx-btn" @click="showInfo = false">{{ $t('PosNext.close') }}</button>
            <button v-if="canAddInfo" type="button" class="posx-pay" @click="addInfoToCart">
              <svg-icon name="plus" /> {{ $t('PosNext.add_to_cart') }}
            </button>
          </div>
        </div>
      </div>
    </b-modal>

    <!-- Camera scanner -->
    <b-modal :content-class="modalClass" v-model="showScanner" hide-footer centered :title="$t('PosNext.scan_title')" dialog-class="posx-dialog-md" @hidden="stopScanner">
      <div :dir="dir" class="posx-scanner">
        <template v-if="scannerSupported">
          <video ref="video" playsinline muted></video>
          <p class="posx-muted">{{ scannerError || $t('PosNext.scan_hint') }}</p>
        </template>
        <p v-else class="posx-muted">{{ $t('PosNext.scan_unsupported') }}</p>
      </div>
    </b-modal>
  </div>
</template>

<script>
import { mapGetters } from "vuex";
import NProgress from "nprogress";
import Util from "./../../../utils";
import { ticketHtml, a5Html, dayListHtml, printHtml } from "./pos_print";

// Small inline SVG icon set (stroke icons, 24x24) so the screen does not depend on an icon font
const ICONS = {
  cart: '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
  trash: '<path d="M3 6h18"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
  search: '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.35-4.35"/>',
  scan: '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  minus: '<path d="M5 12h14"/>',
  x: '<path d="M18 6L6 18M6 6l12 12"/>',
  home: '<path d="M3 10.5L12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>',
  reset: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
  file: '<path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M14 3v6h6M8 13h8M8 17h5"/>',
  pause: '<circle cx="12" cy="12" r="9"/><path d="M10 9v6M14 9v6"/>',
  check: '<path d="M20 6L9 17l-5-5"/>',
  receipt: '<path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2z"/><path d="M9 8h6M9 12h6M9 16h4"/>',
  settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
  globe: '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
  maximize: '<path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/>',
  "user-plus": '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
  warehouse: '<path d="M3 21V8l9-5 9 5v13"/><path d="M7 21v-8h10v8"/><path d="M7 17h10"/>',
  folder: '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
  tag: '<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><circle cx="7" cy="7" r="1.5"/>',
  "chevron-down": '<path d="M6 9l6 6 6-6"/>',
  "chevron-left": '<path d="M15 18l-6-6 6-6"/>',
  "chevron-right": '<path d="M9 18l6-6-6-6"/>',
  box: '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7L12 12l8.7-5M12 22V12"/>',
  printer: '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/>',
  eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>',
  info: '<circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/>',
  lock: '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'
};

const SvgIcon = {
  functional: true,
  props: { name: { type: String, required: true } },
  render(h, { props, data }) {
    return h("svg", {
      ...data,
      class: ["posx-svg", data.class, data.staticClass],
      attrs: {
        viewBox: "0 0 24 24",
        fill: "none",
        stroke: "currentColor",
        "stroke-width": 2,
        "stroke-linecap": "round",
        "stroke-linejoin": "round",
        "aria-hidden": "true"
      },
      domProps: { innerHTML: ICONS[props.name] || "" }
    });
  }
};

const PER_PAGE = 20;
const LS_WAREHOUSE = "posx_warehouse_id";

export default {
  components: { SvgIcon },
  metaInfo() {
    return { title: this.$t("PosNext.title") };
  },

  data() {
    return {
      loadingInit: true,
      loadingProducts: false,
      busy: false,
      online: typeof navigator !== "undefined" ? navigator.onLine : true,

      warehouses: [],
      categories: [],
      brands: [],
      clients: [],
      accounts: [],

      // POS settings of the user (set by the admin in the user form)
      clientLocked: false,
      canAddClient: true,
      // cart permissions of the user (People > Users > Point of sale)
      canEditPrice: false,
      canEditConfirmed: false,
      accessDenied: false,

      warehouse_id: null,
      client_id: null,
      category_id: "",
      brand_id: "",

      search: "",
      searchTimer: null,
      products: [],
      page: 1,
      totalRows: 0,
      pending: {},

      details: [],
      lineSeq: 0,
      total: 0,
      GrandTotal: 0,
      sale: { tax_rate: 0, TaxNet: 0, discount: 0, shipping: 0, notes: "" },

      // cart saved on the server: kept all day until "Confirm sales"
      cartReady: false,
      cartLocked: false,
      cartStartedAt: null,
      saveState: "",
      saveTimer: null,

      // "Confirm sales" window: payment + charges (expenses)
      showConfirm: false,
      payment: { amount: 0, Reglement: "Cash", account_id: null, notes: "" },
      expenseCategories: [],
      chargeMode: "none",
      charges: [],
      chargeSeq: 0,
      showDone: false,
      printing: false,
      lastSale: null,

      showRecent: false,
      loadingDay: false,
      dayDate: "",
      dayData: null,

      showAddCustomer: false,
      newClient: { name: "", phone: "", email: "", city: "", adresse: "" },

      showInfo: false,
      infoSearch: "",
      infoTimer: null,
      infoResults: [],
      infoLoading: false,
      info: null,

      showScanner: false,
      scannerError: "",
      scanStream: null,
      scanTimer: null,

      languageOptions: [
        { code: "ar", name: "العربية" },
        { code: "fr", name: "Français" },
        { code: "en", name: "English" }
      ]
    };
  },

  computed: {
    ...mapGetters(["currentUser", "currentUserPermissions"]),

    isRtl() {
      return this.$i18n.locale === "ar";
    },
    dir() {
      return this.isRtl ? "rtl" : "ltr";
    },
    // user limited to the POS by the admin (user form > Point of sale > POS only)
    posOnly() {
      return !!(this.currentUser && this.currentUser.pos_only);
    },
    // role without the "Point of sale" permission (or refused by the server)
    noPosAccess() {
      if (this.accessDenied) return true;
      // "POS only" users may use the POS even when their role has no "Point of sale" permission
      if (this.posOnly) return false;
      return Array.isArray(this.currentUserPermissions) && !this.currentUserPermissions.includes("Pos_view");
    },
    warehouseLocked() {
      return this.warehouses.length === 1;
    },
    modalClass() {
      return this.isRtl ? "posx-modal posx-modal-rtl" : "posx-modal";
    },
    currency() {
      return this.currentUser && this.currentUser.currency ? this.currentUser.currency : "";
    },
    warehouseName() {
      const w = this.warehouses.find(x => x.id === this.warehouse_id);
      return w ? w.name : "";
    },
    categoryName() {
      const c = this.categories.find(x => x.id === this.category_id);
      return c ? c.name : "";
    },
    brandName() {
      const b = this.brands.find(x => x.id === this.brand_id);
      return b ? b.name : "";
    },
    clientName() {
      const c = this.clients.find(x => x.id === this.client_id);
      return c ? c.name : "";
    },
    clientInitial() {
      return (this.clientName || "?").trim().charAt(0).toUpperCase();
    },
    userInitial() {
      const n = this.currentUser && this.currentUser.username ? this.currentUser.username : "?";
      return n.charAt(0).toUpperCase();
    },
    itemsCount() {
      return this.details.length;
    },
    lastPage() {
      return Math.max(1, Math.ceil(this.totalRows / PER_PAGE));
    },
    pageList() {
      const last = this.lastPage;
      const cur = this.page;
      const pages = new Set([1, last, cur, cur - 1, cur + 1]);
      const list = [...pages].filter(p => p >= 1 && p <= last).sort((a, b) => a - b);
      const out = [];
      list.forEach((p, i) => {
        if (i > 0 && p - list[i - 1] > 1) out.push("…");
        out.push(p);
      });
      return out;
    },
    paymentMethods() {
      return [
        { value: "Cash", label: this.$t("PosNext.method_cash") },
        { value: "tpe", label: this.$t("PosNext.method_tpe") },
        { value: "cheque", label: this.$t("PosNext.method_cheque") },
        { value: "Western Union", label: "Western Union" },
        { value: "bank transfer", label: this.$t("PosNext.method_bank_transfer") },
        { value: "other", label: this.$t("PosNext.method_other") }
      ];
    },
    // confirmed lines this user cannot change (none for users allowed to edit confirmed products)
    confirmedCount() {
      return this.canEditConfirmed ? 0 : this.details.filter(d => d.confirmed).length;
    },
    confirmDue() {
      const d = this.GrandTotal - this.num(this.payment.amount);
      return d > 0 ? this.round(d) : 0;
    },
    chargeModes() {
      return [
        { value: "none", label: this.$t("PosNext.charges_none") },
        { value: "total", label: this.$t("PosNext.charges_total") },
        { value: "lines", label: this.$t("PosNext.charges_lines") }
      ];
    },
    chargesTotal() {
      if (this.chargeMode === "none") return 0;
      return this.round(this.charges.reduce((sum, c) => sum + Math.max(0, this.num(c.amount)), 0));
    },
    // "since 08:12" (or "since 2026-11-10 08:12" when the cart was started on an earlier day)
    cartFromEarlierDay() {
      return !!this.cartStartedAt && String(this.cartStartedAt).slice(0, 10) !== this.todayIso();
    },
    cartSince() {
      if (!this.cartStartedAt || !this.details.length) return "";
      const value = String(this.cartStartedAt);
      const time = value.slice(11, 16);
      return this.cartFromEarlierDay
        ? this.$t("PosNext.cart_since_day", { date: "\u2066" + value.slice(0, 10) + "\u2069", time })
        : this.$t("PosNext.cart_since", { time });
    },
    statusClass() {
      if (!this.online || this.saveState === "error") return "is-offline";
      if (this.saveState === "saving" || this.saveState === "pending") return "is-saving";
      return "is-online";
    },
    statusText() {
      if (!this.online) return this.$t("PosNext.offline");
      if (this.saveState === "error") return this.$t("PosNext.save_failed");
      if (this.saveState === "saving" || this.saveState === "pending") return this.$t("PosNext.saving");
      return this.$t("PosNext.online") + " · " + this.$t("PosNext.saved");
    },
    // price / cost of the product in the warehouse open in the POS (falls back to the product's own values)
    infoRow() {
      if (!this.info) return null;
      return this.info.stock.find(s => s.warehouse_id === this.warehouse_id) || null;
    },
    infoPrice() {
      return this.infoRow ? this.infoRow.price : this.info ? this.info.price : 0;
    },
    infoCost() {
      return this.infoRow ? this.infoRow.cost : this.info ? this.info.cost : 0;
    },
    canAddInfo() {
      const i = this.info;
      return !!(i && i.current && i.type !== "is_variant" && !i.not_selling && !this.isOut(this.infoAsGridItem()));
    },
    scannerSupported() {
      return typeof window !== "undefined" && "BarcodeDetector" in window && !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    }
  },

  watch: {
    // every change of the cart is saved on the server (debounced)
    details: {
      deep: true,
      handler() {
        this.scheduleSave();
      }
    },
    client_id() {
      this.scheduleSave();
    },
    warehouse_id() {
      this.scheduleSave();
    }
  },

  methods: {
    // ------------------------------ helpers ------------------------------
    can(perm) {
      return !!(this.currentUserPermissions && this.currentUserPermissions.includes(perm));
    },
    num(v) {
      const n = parseFloat(v);
      return isNaN(n) ? 0 : n;
    },
    round(v) {
      return Math.round((this.num(v) + Number.EPSILON) * 100) / 100;
    },
    money(v) {
      const s = this.num(v).toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      return this.currency ? this.currency + " " + s : s;
    },
    qty(v) {
      if (v === "---" || v === null || v === undefined) return v;
      const n = this.num(v);
      return Number.isInteger(n) ? String(n) : n.toFixed(2);
    },
    toast(variant, msg) {
      this.$root.$bvToast.toast(msg, {
        title: variant === "success" ? this.$t("PosNext.success") : variant === "danger" ? this.$t("PosNext.error") : this.$t("PosNext.warning"),
        variant,
        solid: true,
        toaster: this.isRtl ? "b-toaster-top-left" : "b-toaster-top-right"
      });
    },
    // the app's axios interceptor (main.js) rejects with the response body, not with the axios error
    errorMessage(error) {
      const data = error && error.response ? error.response.data : error;
      if (data && typeof data === "object") {
        if (data.code && this.$te("PosNext." + data.code)) return this.$t("PosNext." + data.code);
        if (data.message === "This action is unauthorized.") return this.$t("PosNext.not_allowed");
        if (data.message) return data.message;
      }
      return this.$t("PosNext.error");
    },
    // Yes/Cancel dialog in the page language and direction.
    // Values (amount, customer, reference) are isolated with <bdi> so "dh 60.50" or a Latin name
    // does not reorder the Arabic sentence around it.
    confirm(key, params = {}) {
      const esc = s => String(s).replace(/[&<>"']/g, c => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
      const names = Object.keys(params);
      const marked = {};
      names.forEach((name, i) => { marked[name] = "\u0001" + i + "\u0001"; });
      let html = esc(this.$t(key, marked));
      names.forEach((name, i) => {
        html = html.split("\u0001" + i + "\u0001").join("<bdi>" + esc(params[name]) + "</bdi>");
      });
      const dir = this.dir;
      return this.$swal({
        html: '<div dir="' + dir + '">' + html + "</div>",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: this.$t("PosNext.yes"),
        cancelButtonText: this.$t("PosNext.cancel"),
        confirmButtonColor: "#663399",
        // whole popup in the page direction: in Arabic, "Yes" sits on the right
        onOpen: popup => popup.setAttribute("dir", dir)
      }).then(r => !!(r && (r.value || r.isConfirmed)));
    },
    hasImage(p) {
      return p.image && p.image !== "no-image.png" && p.image !== "no_image.png";
    },
    // quantity of a product already in the cart (all its lines, confirmed or not)
    qtyInCart(productId, variantId, exceptLine) {
      return this.details.reduce((sum, d) => {
        if (d === exceptLine) return sum;
        if (d.product_id !== productId || (d.product_variant_id || null) !== (variantId || null)) return sum;
        return sum + this.num(d.quantity);
      }, 0);
    },
    // stock shown on a product card: what is left once the cart is sold
    available(p) {
      if (p.product_type === "is_service") return null;
      return this.round(this.num(p.qte_sale) - this.qtyInCart(p.id, p.product_variant_id));
    },
    isOut(p) {
      return p.product_type !== "is_service" && p.manage_stock !== 0 && p.manage_stock !== false && this.available(p) <= 0;
    },
    isPending(p) {
      return !!this.pending[p.id + "-" + (p.product_variant_id || 0)];
    },
    // confirmed lines are never merged: the same product added again gets a new line
    findLine(productId, variantId) {
      return this.details.find(
        d => !d.confirmed && d.product_id === productId && (d.product_variant_id || null) === (variantId || null)
      );
    },
    inCart(p) {
      return this.qtyInCart(p.id, p.product_variant_id);
    },
    // most a line can hold: the stock minus the other lines of the same product
    lineMax(line) {
      return this.num(line.stock) - this.qtyInCart(line.product_id, line.product_variant_id, line);
    },
    statusLabel(s) {
      const key = "PosNext.status_" + s;
      return this.$te(key) ? this.$t(key) : s;
    },

    // ------------------------------ top bar ------------------------------
    setLocale(locale) {
      this.$i18n.locale = locale;
      this.$store.dispatch("language/setLanguage", locale);
      if (window.Fire) window.Fire.$emit("ChangeLanguage");
    },
    toggleFullScreen() {
      Util.toggleFullScreen();
    },
    logout() {
      this.$store.dispatch("logout");
    },
    async selectWarehouse(id) {
      if (id === this.warehouse_id) return;
      if (this.confirmedCount) {
        this.toast("warning", this.$t("PosNext.warehouse_locked_by_lines"));
        return;
      }
      if (this.details.length) {
        const ok = await this.confirm("PosNext.change_warehouse_confirm");
        if (!ok) return;
        this.resetCart();
      }
      this.warehouse_id = id;
      try { localStorage.setItem(LS_WAREHOUSE, String(id)); } catch (e) { /* storage unavailable */ }
      this.page = 1;
      this.fetchProducts();
    },
    setCategory(id) {
      this.category_id = id;
      this.page = 1;
      this.fetchProducts();
    },
    setBrand(id) {
      this.brand_id = id;
      this.page = 1;
      this.fetchProducts();
    },

    // ------------------------------ loading ------------------------------
    async init() {
      if (this.noPosAccess) return;
      this.loadingInit = true;
      NProgress.start();
      try {
        const { data } = await axios.get("pos/data_create_pos");
        this.warehouses = data.warehouses || [];
        this.categories = data.categories || [];
        this.brands = data.brands || [];
        this.clients = data.clients || [];
        this.accounts = data.accounts || [];
        this.clientLocked = !!data.client_locked;
        this.canAddClient = data.can_add_client !== false;
        this.canEditPrice = !!data.can_edit_price;
        this.canEditConfirmed = !!data.can_edit_confirmed;
        this.expenseCategories = data.expense_categories || [];

        let stored = null;
        try { stored = parseInt(localStorage.getItem(LS_WAREHOUSE), 10); } catch (e) { stored = null; }
        // warehouse chosen by the admin for this user first, then the last one used on this device
        if (data.pos_warehouse && this.warehouses.some(w => w.id === data.pos_warehouse)) {
          this.warehouse_id = data.pos_warehouse;
        } else if (stored && this.warehouses.some(w => w.id === stored)) {
          this.warehouse_id = stored;
        } else if (data.defaultWarehouse) {
          this.warehouse_id = data.defaultWarehouse;
        } else if (this.warehouses.length) {
          this.warehouse_id = this.warehouses[0].id;
        }
        this.client_id = this.defaultClientId(data.defaultClient);

        // the cart of the day, saved on the server, comes back as it was
        await this.restoreCart();
        this.fetchProducts();
      } catch (error) {
        const body = error && error.response ? error.response.data : error;
        if (body && body.message === "This action is unauthorized.") {
          this.accessDenied = true;
        } else {
          this.toast("danger", this.errorMessage(error));
        }
      } finally {
        this.loadingInit = false;
        NProgress.done();
      }
    },
    defaultClientId(fromSettings) {
      if (fromSettings && this.clients.some(c => c.id === fromSettings)) return fromSettings;
      const walkIn = this.clients.find(c => /walk|comptoir|passage/i.test(c.name));
      if (walkIn) return walkIn.id;
      return this.clients.length ? this.clients[0].id : null;
    },
    fetchProducts() {
      if (!this.warehouse_id) return;
      this.loadingProducts = true;
      const params = {
        warehouse_id: this.warehouse_id,
        category_id: this.category_id,
        brand_id: this.brand_id,
        search: this.search.trim(),
        stock: 1,
        product_service: 1,
        page: this.page,
        per_page: PER_PAGE
      };
      const reqId = (this._productsReq = (this._productsReq || 0) + 1);
      return axios
        .get("pos/get_products_pos", { params })
        .then(({ data }) => {
          if (reqId !== this._productsReq) return data;
          this.products = data.products || [];
          this.totalRows = data.totalRows || 0;
          return data;
        })
        .catch(error => {
          this.toast("danger", this.errorMessage(error));
        })
        .finally(() => {
          if (reqId === this._productsReq) this.loadingProducts = false;
        });
    },
    goPage(p) {
      if (p < 1 || p > this.lastPage || p === this.page) return;
      this.page = p;
      this.fetchProducts();
    },

    // ------------------------------ search / scan ------------------------------
    onSearchInput() {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => {
        this.page = 1;
        this.fetchProducts();
      }, 350);
    },
    clearSearch() {
      this.search = "";
      this.page = 1;
      this.fetchProducts();
      this.$refs.search && this.$refs.search.focus();
    },
    // Enter in the search box (keyboard or barcode scanner): add the exact code match
    async onSearchEnter() {
      clearTimeout(this.searchTimer);
      const code = this.search.trim();
      if (!code) return;
      this.page = 1;
      const data = await this.fetchProducts();
      const list = (data && data.products) || this.products;
      const lc = code.toLowerCase();
      const exact = list.filter(p => String(p.code).toLowerCase() === lc || String(p.barcode).toLowerCase() === lc);
      const hit = exact.length === 1 ? exact[0] : list.length === 1 ? list[0] : null;
      if (hit) {
        await this.addProduct(hit);
        this.search = "";
        this.fetchProducts();
      } else if (!list.length) {
        this.toast("warning", this.$t("PosNext.not_found_code", { code }));
      }
    },
    // ------------------------------ product info ------------------------------
    openProductInfo(productId) {
      this.info = null;
      this.showInfo = true;
      if (productId) {
        this.infoSearch = "";
        this.infoResults = [];
        this.loadProductInfo(productId);
      }
    },
    focusInfoSearch() {
      if (!this.info && this.$refs.infoSearch) this.$refs.infoSearch.focus();
    },
    onInfoSearch() {
      clearTimeout(this.infoTimer);
      this.infoTimer = setTimeout(() => this.fetchInfoResults(), 300);
    },
    fetchInfoResults() {
      const search = this.infoSearch.trim();
      this.info = null;
      if (!search) {
        this.infoResults = [];
        return Promise.resolve([]);
      }
      this.infoLoading = true;
      const reqId = (this._infoReq = (this._infoReq || 0) + 1);
      return axios
        .get("pos/product_lookup", { params: { search } })
        .then(({ data }) => {
          if (reqId !== this._infoReq) return [];
          this.infoResults = data.products || [];
          return this.infoResults;
        })
        .catch(error => {
          this.toast("danger", this.errorMessage(error));
          return [];
        })
        .finally(() => {
          if (reqId === this._infoReq) this.infoLoading = false;
        });
    },
    // Enter (keyboard or barcode scanner): open the product directly when the code matches
    async onInfoEnter() {
      clearTimeout(this.infoTimer);
      const code = this.infoSearch.trim().toLowerCase();
      const list = await this.fetchInfoResults();
      const hit = list.find(r => String(r.code).toLowerCase() === code) || (list.length === 1 ? list[0] : null);
      if (hit) this.loadProductInfo(hit.id);
    },
    clearInfoSearch() {
      this.infoSearch = "";
      this.infoResults = [];
      this.info = null;
      this.$refs.infoSearch && this.$refs.infoSearch.focus();
    },
    loadProductInfo(id) {
      this.infoLoading = true;
      return axios
        .get(`pos/product_info/${id}`, { params: { warehouse_id: this.warehouse_id } })
        .then(({ data }) => {
          this.info = data;
        })
        .catch(error => this.toast("danger", this.errorMessage(error)))
        .finally(() => {
          this.infoLoading = false;
        });
    },
    infoAsGridItem() {
      const i = this.info;
      return {
        id: i.id,
        product_variant_id: null,
        image: i.image,
        product_type: i.type,
        unitSale: i.unit_sale,
        qte_sale: i.current ? i.current.qte_sale : 0,
        manage_stock: i.current ? i.current.manage_stock : 1
      };
    },
    async addInfoToCart() {
      await this.addProduct(this.infoAsGridItem());
      this.showInfo = false;
    },

    openScanner() {
      this.scannerError = "";
      this.showScanner = true;
      if (!this.scannerSupported) return;
      this.$nextTick(async () => {
        try {
          this.scanStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } });
          const video = this.$refs.video;
          if (!video) return this.stopScanner();
          video.srcObject = this.scanStream;
          await video.play();
          // eslint-disable-next-line no-undef
          const detector = new BarcodeDetector();
          const tick = async () => {
            if (!this.showScanner || !this.scanStream) return;
            try {
              const codes = await detector.detect(video);
              if (codes && codes.length) {
                const value = codes[0].rawValue;
                this.showScanner = false;
                this.stopScanner();
                this.search = value;
                this.onSearchEnter();
                return;
              }
            } catch (e) { /* frame not ready */ }
            this.scanTimer = setTimeout(tick, 250);
          };
          tick();
        } catch (e) {
          this.scannerError = this.$t("PosNext.camera_error");
        }
      });
    },
    stopScanner() {
      clearTimeout(this.scanTimer);
      if (this.scanStream) {
        this.scanStream.getTracks().forEach(t => t.stop());
        this.scanStream = null;
      }
    },

    // ------------------------------ cart ------------------------------
    async addProduct(p) {
      if (!this.warehouse_id) {
        this.toast("warning", this.$t("PosNext.select_warehouse"));
        return;
      }
      const existing = this.findLine(p.id, p.product_variant_id);
      if (existing) {
        this.increment(existing);
        return;
      }
      if (this.isOut(p)) {
        this.toast("warning", this.$t("PosNext.low_stock"));
        return;
      }
      const key = p.id + "-" + (p.product_variant_id || 0);
      if (this.pending[key]) return;
      this.$set(this.pending, key, true);
      try {
        const variant = p.product_variant_id ? p.product_variant_id : "null";
        const { data } = await axios.get(`show_product_data/${p.id}/${variant}/${this.warehouse_id}`);
        if (this.findLine(p.id, p.product_variant_id)) {
          this.increment(this.findLine(p.id, p.product_variant_id));
          return;
        }
        const isService = data.product_type === "is_service";
        const stock = isService ? null : this.num(p.qte_sale);
        const line = {
          detail_id: ++this.lineSeq,
          uid: this.newUid(),
          confirmed: false,
          added_at: this.nowStamp(),
          confirmed_at: null,
          product_id: data.id,
          product_variant_id: p.product_variant_id || null,
          name: data.name,
          code: data.code,
          image: p.image,
          product_type: data.product_type,
          manage_stock: p.manage_stock,
          stock,
          unitSale: data.unitSale || p.unitSale || "",
          sale_unit_id: data.sale_unit_id || null,
          is_imei: !!data.is_imei,
          imei_number: "",
          Unit_price: this.num(data.Unit_price),
          tax_percent: this.num(data.tax_percent),
          tax_method: String(data.tax_method || "1"),
          discount: 0,
          discount_Method: "2",
          DiscountNet: 0,
          Net_price: 0,
          taxe: 0,
          quantity: !isService && stock !== null && stock > 0 && stock < 1 ? stock : 1,
          subtotal: 0
        };
        this.computeLinePrice(line);
        // price at the time the product was added, to show when the cashier changes it
        line.base_price = this.round(this.linePrice(line));
        this.details.push(line);
        this.calcTotal();
      } catch (error) {
        this.toast("danger", this.errorMessage(error));
      } finally {
        this.$delete(this.pending, key);
      }
    },
    computeLinePrice(line) {
      const price = this.num(line.Unit_price);
      const taxPct = this.num(line.tax_percent);
      line.DiscountNet = line.discount_Method === "2" ? this.num(line.discount) : (price * this.num(line.discount)) / 100;
      if (line.tax_method === "1") {
        line.Net_price = price - line.DiscountNet;
        line.taxe = (taxPct * (price - line.DiscountNet)) / 100;
      } else {
        line.Net_price = (price - line.DiscountNet) / (taxPct / 100 + 1);
        line.taxe = price - line.Net_price - line.DiscountNet;
      }
    },
    // unit price shown in the cart (what the customer pays for one unit)
    linePrice(line) {
      return this.num(line.Net_price) + this.num(line.taxe);
    },
    priceChanged(line) {
      return line.base_price !== null && line.base_price !== undefined && Math.abs(this.linePrice(line) - line.base_price) > 0.004;
    },
    // the cashier types the price paid for one unit; Unit_price is worked back from it
    // locked for this user: confirmed, and no permission to edit confirmed products
    lineLocked(line) {
      return !!line.confirmed && !this.canEditConfirmed;
    },
    setPrice(line, value) {
      if (!this.canEditPrice || this.lineLocked(line)) return;
      let price = this.num(value);
      if (price < 0) price = 0;
      const tax = this.num(line.tax_percent) / 100;
      const discount = this.num(line.DiscountNet);
      line.Unit_price = line.tax_method === "1" ? price / (1 + tax) + discount : price + discount;
      this.computeLinePrice(line);
      this.calcTotal();
    },
    limitedByStock(line) {
      return line.product_type !== "is_service" && line.stock !== null && line.manage_stock !== 0 && line.manage_stock !== false;
    },
    newUid() {
      return Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
    },
    // "2026-11-11 10:42:05" -> "10:42" (with the date when it is not today)
    shortTime(value) {
      if (!value) return "";
      const v = String(value);
      const time = v.slice(11, 16);
      return v.slice(0, 10) === this.todayIso() ? time : v.slice(0, 10) + " " + time;
    },
    lineTimesTitle(line) {
      const parts = [];
      if (line.added_at) parts.push(this.$t("PosNext.added_at", { time: this.shortTime(line.added_at) }));
      if (line.confirmed && line.confirmed_at) parts.push(this.$t("PosNext.confirmed_at", { time: this.shortTime(line.confirmed_at) }));
      return parts.join(" · ");
    },
    // local time until the server sends its own (see saveCartNow)
    nowStamp() {
      const d = new Date();
      const p = n => String(n).padStart(2, "0");
      return this.todayIso() + " " + p(d.getHours()) + ":" + p(d.getMinutes()) + ":" + p(d.getSeconds());
    },
    async toggleLineConfirmed(line) {
      // a confirmed product is locked for good
      if (line.confirmed) return;
      if (!(this.num(line.quantity) > 0)) {
        this.toast("warning", this.$t("PosNext.invalid_qty"));
        return;
      }
      if (line.is_imei && !line.imei_number) {
        const ok = await this.confirm("PosNext.confirm_line_no_imei", { name: line.name });
        if (!ok) return;
      }
      line.confirmed_at = this.nowStamp();
      line.confirmed = true;
    },
    increment(line) {
      if (this.lineLocked(line)) return;
      const next = this.num(line.quantity) + 1;
      if (this.limitedByStock(line) && next > this.lineMax(line)) {
        this.toast("warning", this.$t("PosNext.low_stock"));
        return;
      }
      line.quantity = next;
      this.calcTotal();
    },
    decrement(line) {
      if (this.lineLocked(line)) return;
      const next = this.num(line.quantity) - 1;
      if (next <= 0) return;
      line.quantity = next;
      this.calcTotal();
    },
    setQuantity(line, value) {
      if (this.lineLocked(line)) return;
      let q = this.num(value);
      if (q <= 0) q = 1;
      if (this.limitedByStock(line) && q > this.lineMax(line)) {
        this.toast("warning", this.$t("PosNext.low_stock"));
        q = Math.max(this.lineMax(line), 0);
        if (q <= 0) q = this.num(line.quantity);
      }
      line.quantity = q;
      this.calcTotal();
    },
    removeLine(line) {
      if (this.lineLocked(line)) return;
      this.details = this.details.filter(d => d.detail_id !== line.detail_id);
      this.calcTotal();
    },
    calcTotal() {
      let total = 0;
      this.details.forEach(d => {
        d.subtotal = this.num(d.quantity) * d.Net_price + d.taxe * this.num(d.quantity);
        total += d.subtotal;
      });
      this.total = total;
      const afterDiscount = total - this.num(this.sale.discount);
      this.sale.TaxNet = (afterDiscount * this.num(this.sale.tax_rate)) / 100;
      this.GrandTotal = this.round(afterDiscount + this.sale.TaxNet + this.num(this.sale.shipping));
    },
    resetCart() {
      this.details = [];
      this.sale = { tax_rate: 0, TaxNet: 0, discount: 0, shipping: 0, notes: "" };
      this.cartStartedAt = null;
      this.calcTotal();
    },
    async confirmReset() {
      if (!this.details.length) return;
      if (this.confirmedCount) {
        // confirmed products stay: only the others are removed
        if (this.confirmedCount === this.details.length) {
          this.toast("warning", this.$t("PosNext.reset_only_confirmed"));
          return;
        }
        const ok = await this.confirm("PosNext.reset_keep_confirmed", { n: this.confirmedCount });
        if (!ok) return;
        this.details = this.details.filter(d => d.confirmed);
        this.calcTotal();
        return;
      }
      const ok = await this.confirm("PosNext.reset_confirm");
      if (ok) this.resetCart();
    },
    validateCart() {
      if (!this.details.length) {
        this.toast("warning", this.$t("PosNext.cart_empty"));
        return false;
      }
      if (!this.warehouse_id) {
        this.toast("warning", this.$t("PosNext.select_warehouse"));
        return false;
      }
      if (!this.client_id) {
        this.toast("warning", this.$t("PosNext.select_customer"));
        return false;
      }
      for (const d of this.details) {
        if (!(this.num(d.quantity) > 0)) {
          this.toast("warning", this.$t("PosNext.invalid_qty"));
          return false;
        }
        if (this.limitedByStock(d) && this.num(d.quantity) > this.lineMax(d)) {
          this.toast("warning", this.$t("PosNext.low_stock") + " — " + d.name);
          return false;
        }
      }
      return true;
    },
    orderPayload() {
      return {
        client_id: this.client_id,
        warehouse_id: this.warehouse_id,
        tax_rate: this.num(this.sale.tax_rate),
        TaxNet: this.round(this.sale.TaxNet),
        discount: this.num(this.sale.discount),
        shipping: this.num(this.sale.shipping),
        GrandTotal: this.GrandTotal,
        notes: this.sale.notes,
        details: this.details.map(d => ({
          product_id: d.product_id,
          product_variant_id: d.product_variant_id,
          sale_unit_id: d.sale_unit_id,
          quantity: this.num(d.quantity),
          subtotal: d.subtotal,
          Unit_price: d.Unit_price,
          tax_percent: d.tax_percent,
          tax_method: d.tax_method,
          discount: d.discount,
          discount_Method: d.discount_Method,
          imei_number: d.imei_number || null,
          added_at: d.added_at || null,
          confirmed_at: d.confirmed ? d.confirmed_at || null : null
        }))
      };
    },

    // ------------------------------ confirm sales (payment + charges) ------------------------------
    openConfirm() {
      if (!this.validateCart()) return;
      this.payment = { amount: this.GrandTotal, Reglement: "Cash", account_id: null, notes: "" };
      this.chargeMode = "none";
      this.charges = [];
      this.showConfirm = true;
    },
    newChargeLine() {
      return { key: ++this.chargeSeq, amount: null, category_id: null, details: "" };
    },
    setChargeMode(mode) {
      this.chargeMode = mode;
      if (mode === "none") {
        this.charges = [];
      } else if (mode === "total") {
        this.charges = [this.charges[0] || this.newChargeLine()];
      } else if (!this.charges.length) {
        this.charges = [this.newChargeLine()];
      }
    },
    addChargeLine() {
      this.charges.push(this.newChargeLine());
    },
    removeChargeLine(index) {
      if (this.charges.length > 1) this.charges.splice(index, 1);
    },
    async submitConfirm() {
      if (!this.validateCart()) return;
      const paying = this.round(this.payment.amount);
      if (!(paying >= 0) || paying > this.GrandTotal) {
        this.toast("warning", this.$t("PosNext.paying_gt_total"));
        return;
      }
      let charges = [];
      if (this.chargeMode !== "none") {
        // empty rows are ignored; a started row needs an amount and a category
        const rows = this.charges.filter(c => this.num(c.amount) > 0 || c.category_id || c.details);
        if (rows.some(c => !(this.num(c.amount) > 0) || !c.category_id)) {
          this.toast("warning", this.$t("PosNext.charge_invalid"));
          return;
        }
        charges = rows.map(c => ({ amount: this.round(c.amount), category_id: c.category_id, details: c.details || "" }));
      }
      this.postSale(paying, {
        Reglement: this.payment.Reglement,
        account_id: this.payment.account_id,
        notes: ""
      }, charges);
    },
    async postSale(paying, payment, charges) {
      const payload = {
        ...this.orderPayload(),
        amount: paying,
        change: 0,
        payment: { amount: paying, ...payment },
        charges,
        clear_cart: true
      };
      // no autosave while the sale is being created, and none still on its way to the server
      this.cartLocked = true;
      clearTimeout(this.saveTimer);
      if (this._savePromise) await this._savePromise.catch(() => {});
      this.busy = true;
      NProgress.start();
      axios
        .post("pos/create_pos", payload)
        .then(({ data }) => {
          this.lastSale = {
            id: data.id,
            total: this.GrandTotal,
            paid: paying,
            change: 0,
            due: this.round(this.GrandTotal - paying),
            charges: charges.reduce((sum, c) => sum + c.amount, 0)
          };
          this.showConfirm = false;
          // the server already emptied the saved cart
          this.resetCart();
          this.saveState = "saved";
          this.$nextTick(() => {
            this.cartLocked = false;
          });
          this.fetchProducts();
          this.showDone = true;
        })
        .catch(error => {
          this.cartLocked = false;
          this.toast("danger", this.errorMessage(error));
        })
        .finally(() => {
          this.busy = false;
          NProgress.done();
        });
    },
    // Ticket (80 mm thermal printer) or A5 invoice, printed from the browser
    printSale(id, format) {
      if (this.printing) return;
      this.printing = true;
      axios
        .get(`pos/sale_print/${id}`)
        .then(({ data }) => {
          const options = {
            t: (key, params) => this.$t(key, params),
            money: v => this.money(v),
            dir: this.dir,
            lang: this.$i18n.locale,
            origin: window.location.origin
          };
          return printHtml(format === "a5" ? a5Html(data, options) : ticketHtml(data, options));
        })
        .catch(error => this.toast("danger", this.errorMessage(error)))
        .finally(() => {
          this.printing = false;
        });
    },
    downloadInvoice(id) {
      NProgress.start();
      axios
        .get(`sale_pdf/${id}`, { responseType: "blob" })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data], { type: "application/pdf" }));
          window.open(url, "_blank");
        })
        .catch(error => this.toast("danger", this.errorMessage(error)))
        .finally(() => NProgress.done());
    },
    openSale(id) {
      const { href } = this.$router.resolve(`/app/sales/detail/${id}`);
      window.open(href, "_blank");
    },

    // ------------------------------ cart saved on the server ------------------------------
    todayIso() {
      const d = new Date();
      return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
    },
    serializeLines() {
      return this.details.map(d => ({
        uid: d.uid,
        confirmed: !!d.confirmed,
        added_at: d.added_at,
        confirmed_at: d.confirmed_at,
        product_id: d.product_id,
        product_variant_id: d.product_variant_id,
        name: d.name,
        code: d.code,
        image: d.image,
        product_type: d.product_type,
        unitSale: d.unitSale,
        sale_unit_id: d.sale_unit_id,
        is_imei: d.is_imei,
        imei_number: d.imei_number,
        Unit_price: d.Unit_price,
        base_price: d.base_price,
        tax_percent: d.tax_percent,
        tax_method: d.tax_method,
        discount: d.discount,
        discount_Method: d.discount_Method,
        quantity: this.num(d.quantity)
      }));
    },
    scheduleSave() {
      if (!this.cartReady || this.cartLocked) return;
      this.saveState = "pending";
      clearTimeout(this.saveTimer);
      this.saveTimer = setTimeout(() => this.saveCartNow(), 600);
    },
    // one save at a time; a change made during a save is sent right after it
    saveCartNow() {
      clearTimeout(this.saveTimer);
      if (!this.cartReady || this.cartLocked) return Promise.resolve();
      if (this._savePromise) {
        this._saveAgain = true;
        return this._savePromise;
      }
      const body = { warehouse_id: this.warehouse_id, client_id: this.client_id, lines: this.serializeLines() };
      this.saveState = "saving";
      this._savePromise = axios
        .put("pos/cart", body)
        .then(({ data }) => {
          this.saveState = "saved";
          this.cartStartedAt = body.lines.length ? data.started_at || this.cartStartedAt : null;
          // times are stamped by the server
          const stamps = data.stamps || {};
          this.details.forEach(d => {
            const st = stamps[d.uid];
            if (st) {
              d.added_at = st.added_at;
              d.confirmed_at = st.confirmed_at;
            }
          });
        })
        .catch(error => {
          const data = error && error.response ? error.response.data : error;
          if (data && (data.code === "pos_line_locked" || data.code === "pos_price_locked")) {
            // the saved cart wins: reload it
            this.toast("warning", this.$t("PosNext." + data.code));
            this.cartReady = false;
            this.restoreCart();
            return;
          }
          this.saveState = "error";
          // try again a bit later (connection lost...)
          this.saveTimer = setTimeout(() => this.saveCartNow(), 5000);
        })
        .finally(() => {
          this._savePromise = null;
          if (this._saveAgain) {
            this._saveAgain = false;
            this.saveCartNow();
          }
        });
      return this._savePromise;
    },
    cartLineFromSaved(l) {
      const isService = l.product_type === "is_service";
      const line = {
        detail_id: ++this.lineSeq,
        uid: l.uid || this.newUid(),
        confirmed: !!l.confirmed,
        added_at: l.added_at || null,
        confirmed_at: l.confirmed_at || null,
        product_id: l.product_id,
        product_variant_id: l.product_variant_id || null,
        name: l.name,
        code: l.code,
        image: l.image || null,
        product_type: l.product_type,
        manage_stock: l.manage_stock === undefined ? 1 : l.manage_stock,
        stock: isService || l.stock === null || l.stock === undefined ? null : this.num(l.stock),
        unitSale: l.unitSale || "",
        sale_unit_id: l.sale_unit_id || null,
        is_imei: !!l.is_imei,
        imei_number: l.imei_number || "",
        Unit_price: this.num(l.Unit_price),
        base_price: l.base_price === null || l.base_price === undefined ? null : this.num(l.base_price),
        tax_percent: this.num(l.tax_percent),
        tax_method: String(l.tax_method || "1"),
        discount: this.num(l.discount),
        discount_Method: String(l.discount_Method || "2"),
        DiscountNet: 0,
        Net_price: 0,
        taxe: 0,
        quantity: this.num(l.quantity) || 1,
        subtotal: 0
      };
      this.computeLinePrice(line);
      return line;
    },
    async restoreCart() {
      try {
        const { data } = await axios.get("pos/cart");
        const cart = data.cart;
        if (cart && cart.lines && cart.lines.length) {
          if (cart.warehouse_id && this.warehouses.some(w => w.id === cart.warehouse_id)) this.warehouse_id = cart.warehouse_id;
          if (cart.client_id && this.clients.some(c => c.id === cart.client_id)) this.client_id = cart.client_id;
          this.lineSeq = 0;
          this.details = cart.lines.map(l => this.cartLineFromSaved(l));
          this.cartStartedAt = cart.started_at;
          this.calcTotal();
        }
        this.saveState = "saved";
        await this.$nextTick();
        // from now on every change is saved
        this.cartReady = true;
      } catch (error) {
        // never save over a cart we could not read
        this.saveState = "error";
        this.toast("danger", this.$t("PosNext.cart_load_failed"));
      }
    },
    // closing the tab while the last change is not saved yet: ask first
    onBeforeUnload(event) {
      if (this.saveState === "pending" || this.saveState === "saving") {
        event.preventDefault();
        event.returnValue = "";
      }
    },

    // ------------------------------ recent sales ------------------------------
    openRecentSales() {
      this.showRecent = true;
      if (!this.dayDate) this.dayDate = this.todayIso();
      this.loadDayProducts();
    },
    shiftDay(days) {
      const d = new Date((this.dayDate || this.todayIso()) + "T12:00:00");
      d.setDate(d.getDate() + days);
      const p = n => String(n).padStart(2, "0");
      const next = d.getFullYear() + "-" + p(d.getMonth() + 1) + "-" + p(d.getDate());
      if (next > this.todayIso()) return;
      this.dayDate = next;
      this.loadDayProducts();
    },
    loadDayProducts() {
      this.loadingDay = true;
      const reqId = (this._dayReq = (this._dayReq || 0) + 1);
      axios
        .get("pos/day_products", { params: { date: this.dayDate } })
        .then(({ data }) => {
          if (reqId === this._dayReq) this.dayData = data;
        })
        .catch(error => this.toast("danger", this.errorMessage(error)))
        .finally(() => {
          if (reqId === this._dayReq) this.loadingDay = false;
        });
    },
    printDayList(format) {
      if (!this.dayData) return;
      const options = {
        t: (key, params) => this.$t(key, params),
        money: v => this.money(v),
        dir: this.dir,
        lang: this.$i18n.locale,
        origin: window.location.origin
      };
      printHtml(dayListHtml(this.dayData, options, format));
    },

    // ------------------------------ customers ------------------------------
    openAddCustomer() {
      this.newClient = { name: "", phone: "", email: "", city: "", adresse: "" };
      this.showAddCustomer = true;
      this.$nextTick(() => this.$refs.newClientName && this.$refs.newClientName.focus());
    },
    saveCustomer() {
      if (!this.newClient.name) return;
      this.busy = true;
      axios
        .post("clients", { ...this.newClient, remise: 0, credit_initial: 0 })
        .then(async ({ data }) => {
          const res = await axios.get("pos/data_create_pos");
          this.clients = res.data.clients || this.clients;
          if (data.id) {
            this.client_id = data.id;
          } else {
            const match = this.clients.filter(c => c.name === this.newClient.name).sort((a, b) => b.id - a.id)[0];
            if (match) this.client_id = match.id;
          }
          this.showAddCustomer = false;
          this.toast("success", this.$t("PosNext.customer_created"));
        })
        .catch(error => this.toast("danger", this.errorMessage(error)))
        .finally(() => {
          this.busy = false;
        });
    },

    // ------------------------------ connectivity ------------------------------
    setOnline() {
      this.online = true;
    },
    setOffline() {
      this.online = false;
    }
  },

  created() {
    this.init();
  },
  mounted() {
    window.addEventListener("online", this.setOnline);
    window.addEventListener("offline", this.setOffline);
    window.addEventListener("beforeunload", this.onBeforeUnload);
  },
  beforeDestroy() {
    window.removeEventListener("online", this.setOnline);
    window.removeEventListener("offline", this.setOffline);
    window.removeEventListener("beforeunload", this.onBeforeUnload);
    // leaving the POS (Home...): send the last change now
    if (this.saveState === "pending") this.saveCartNow();
    clearTimeout(this.searchTimer);
    clearTimeout(this.infoTimer);
    this.stopScanner();
  }
};
</script>

<style scoped>
.posx {
  position: fixed;
  text-align: start;
  inset: 0;
  display: flex;
  flex-direction: column;
  background: var(--px-bg);
  color: var(--px-text);
  font-size: 14px;
  z-index: 10;
}
.posx-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; }
.posx-muted { color: var(--px-muted); }
.posx-spacer { flex: 1 1 auto; }
.posx-svg { width: 18px; height: 18px; flex: 0 0 auto; }
.posx-rtl .posx-flip { transform: scaleX(-1); }
.posx-center { display: flex; justify-content: center; padding: 24px; }
.posx-text-danger { color: var(--px-danger); }

/* ---------- top bar ---------- */
.posx-top {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: var(--px-surface);
  border-bottom: 1px solid var(--px-border);
  flex-wrap: wrap;
}
.posx-brand { display: flex; align-items: center; gap: 8px; padding-inline-end: 6px; }
.posx-brand img { width: 34px; height: 34px; object-fit: contain; border-radius: 8px; }
.posx-brand-title { font-weight: 700; font-size: 15px; white-space: nowrap; }
.posx-dd >>> .posx-pill {
  display: flex;
  align-items: center;
  gap: 8px;
  height: 40px;
  padding: 0 12px;
  border: 1px solid var(--px-border);
  border-radius: var(--px-radius);
  background: var(--px-surface);
  color: var(--px-text);
  text-decoration: none;
  box-shadow: none;
}
.posx-dd >>> .posx-pill:hover { border-color: var(--px-primary-100); background: var(--px-primary-50); }
.posx-dd >>> .posx-pill-warehouse { background: var(--px-primary-50); border-color: var(--px-primary-100); }
.posx-pill-icon { color: var(--px-primary); }
.posx-pill-icon-solid {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 8px; background: var(--px-primary); color: #fff;
}
.posx-pill-icon-solid .posx-svg { width: 16px; height: 16px; }
.posx-pill-text { display: flex; flex-direction: column; line-height: 1.1; text-align: start; }
.posx-pill-text small { font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: var(--px-primary); font-weight: 600; }
.posx-pill-text strong { max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 13px; }
.posx-pill-label { max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.posx-caret { width: 14px; height: 14px; color: var(--px-muted); }
.posx-dd >>> .posx-dd-scroll { max-height: 320px; overflow-y: auto; }
.posx-dd >>> .dropdown-item.active { background: var(--px-primary); }

.posx-customer {
  display: flex; align-items: center; gap: 8px;
  height: 44px; padding: 0 8px; min-width: 230px;
  border: 1px solid #f6d6dc; background: #fff5f7; border-radius: var(--px-radius);
}
.posx-avatar {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: 50%; background: var(--px-success); color: #fff; font-weight: 700; font-size: 13px;
}
.posx-customer-body { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.posx-customer-body small { font-size: 10px; text-transform: uppercase; letter-spacing: .04em; color: #be123c; font-weight: 600; line-height: 1; }
.posx-vselect { min-width: 170px; }
.posx-vselect >>> .vs__dropdown-toggle { border: 0; padding: 0; background: transparent; }
.posx-vselect >>> .vs__selected { margin: 0; padding: 0; font-weight: 700; font-size: 13px; }
.posx-vselect >>> .vs__search { margin: 0; padding: 0; font-size: 13px; }
.posx-vselect >>> .vs__actions { padding: 0 2px; }
.posx-vselect >>> .vs__selected-options { flex-wrap: nowrap; min-width: 0; }
.posx-vselect >>> .vs__selected { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
.posx-vselect.vs--open >>> .vs__selected { opacity: .35; }
/* dropdown: wider than the pill, aligned with its outer edge, readable rows */
.posx-vselect >>> .vs__dropdown-menu {
  top: calc(100% + 12px);
  left: auto;
  right: auto;
  inset-inline-start: -47px;
  width: 320px;
  max-width: calc(100vw - 24px);
  max-height: 360px;
  padding: 6px;
  border: 1px solid var(--px-border);
  border-radius: 12px;
  background: var(--px-surface);
  box-shadow: 0 12px 32px rgba(31, 31, 46, .14);
  text-align: start;
  z-index: 1060;
}
.posx-vselect >>> .vs__dropdown-option {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 7px 8px;
  border-radius: 8px;
  color: var(--px-text);
  font-size: 13px;
  white-space: normal;
  line-height: 1.3;
}
.posx-vselect >>> .vs__dropdown-option--highlight { background: var(--px-primary-50); color: var(--px-primary); }
.posx-vselect >>> .vs__dropdown-option--selected { font-weight: 700; }
.posx-vselect >>> .vs__dropdown-option--selected .posx-opt-avatar { background: var(--px-primary); color: #fff; }
.posx-vselect >>> .vs__no-options { padding: 10px; color: var(--px-muted); }
.posx-opt-avatar {
  display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto;
  width: 26px; height: 26px; border-radius: 50%;
  background: var(--px-bg); color: var(--px-muted); font-size: 11px; font-weight: 700;
}
.posx-opt-name { flex: 1; min-width: 0; overflow-wrap: anywhere; }
.posx-customer-fixed { display: flex; align-items: center; gap: 6px; font-size: 13px; min-width: 0; }
.posx-customer-fixed span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px; }
.posx-customer-fixed .posx-svg { width: 13px; height: 13px; color: #be123c; }
.posx-dd.is-locked >>> .posx-pill { opacity: 1; cursor: default; }
.posx-denied {
  position: absolute; inset: 0; z-index: 60;
  display: flex; align-items: center; justify-content: center; padding: 24px;
  background: var(--px-bg); text-align: center;
}
.posx-denied h4 { margin: 14px 0 6px; font-weight: 700; }
.posx-denied p { color: var(--px-muted); max-width: 420px; margin: 0 auto 18px; }
.posx-denied-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 64px; height: 64px; border-radius: 50%; background: var(--px-primary-50); color: var(--px-primary);
}
.posx-denied-icon .posx-svg { width: 30px; height: 30px; }
.posx-currency {
  display: inline-flex; align-items: center; height: 40px; padding: 0 12px;
  border-radius: var(--px-radius); background: var(--px-primary-50); color: var(--px-primary); font-weight: 700; font-size: 13px;
}
.posx-icon-group { display: flex; gap: 4px; }
.posx-icon-btn, .posx-dd >>> .posx-icon-btn {
  display: inline-flex; align-items: center; justify-content: center;
  width: 40px; height: 40px; padding: 0;
  border: 1px solid var(--px-border); border-radius: var(--px-radius);
  background: var(--px-surface); color: var(--px-text); cursor: pointer;
}
.posx-icon-btn:hover:not(:disabled), .posx-dd >>> .posx-icon-btn:hover { background: var(--px-primary-50); color: var(--px-primary); }
.posx-icon-btn:disabled { opacity: .45; cursor: not-allowed; }
.posx-icon-btn-sm { width: 32px; height: 32px; }
.posx-icon-btn-sm .posx-svg { width: 16px; height: 16px; }
.posx-danger { color: var(--px-danger); }
.posx-dd >>> .posx-user-btn { padding: 0; border: 0; text-decoration: none; }
.posx-user {
  display: inline-flex; align-items: center; justify-content: center;
  width: 40px; height: 40px; border-radius: 50%; background: var(--px-primary); color: #fff; font-weight: 700;
}

/* ---------- main ---------- */
.posx-main { flex: 1 1 auto; display: flex; min-height: 0; }
.posx-cart {
  flex: 0 0 46%; max-width: 640px; display: flex; flex-direction: column; min-height: 0;
  background: var(--px-surface); border-inline-end: 1px solid var(--px-border);
}
.posx-cart-head { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-bottom: 1px solid var(--px-border); }
.posx-cart-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 34px; height: 34px; border-radius: 8px; background: var(--px-primary-50); color: var(--px-primary);
}
.posx-cart-title { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.posx-cart-title small { color: var(--px-muted); }
.posx-since { margin-inline-start: 6px; padding: 1px 6px; border-radius: 6px; background: var(--px-primary-50); color: var(--px-primary); font-weight: 600; }
.posx-since.is-old { background: #fff7ed; color: #c2410c; }
.posx-price { display: flex; margin: 0; }
.posx-price input {
  width: 100%; height: 32px; padding: 0 6px; border: 1px solid var(--px-border); border-radius: 8px;
  text-align: center; font-weight: 600; background: var(--px-surface); -moz-appearance: textfield;
}
.posx-price input::-webkit-outer-spin-button, .posx-price input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.posx-price input:focus { outline: 0; border-color: var(--px-primary); box-shadow: 0 0 0 3px var(--px-primary-50); }
.posx-price.is-changed input { border-color: #f59e0b; background: #fffbeb; }
.posx-old-price s { color: var(--px-muted); }
.posx-stock-low { color: var(--px-danger); font-weight: 700; }
.posx-time { display: inline-flex; align-items: center; gap: 3px; padding: 0 6px; border-radius: 6px; background: var(--px-bg); color: var(--px-text); font-weight: 600; }
.posx-time .posx-svg { width: 12px; height: 12px; }
.posx-line.is-confirmed .posx-time { background: #dcfce7; color: #15803d; }
.posx-cart-total { display: flex; flex-direction: column; align-items: flex-end; line-height: 1.15; }
.posx-cart-total small { font-size: 10px; text-transform: uppercase; color: var(--px-muted); letter-spacing: .04em; }
.posx-cart-total strong { font-size: 16px; }
.posx-cart-lines { flex: 1 1 auto; overflow-y: auto; padding: 8px 12px; }
.posx-line {
  display: grid; grid-template-columns: minmax(0, 1fr) 86px auto 92px 30px 28px; align-items: center; gap: 8px;
  padding: 10px; border: 1px solid var(--px-border); border-radius: var(--px-radius); margin-bottom: 8px;
}
.posx-line-name { font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.posx-line-meta { color: var(--px-muted); font-size: 12px; display: flex; flex-wrap: wrap; gap: 4px; }
.posx-imei {
  margin-top: 6px; width: 100%; height: 30px; padding: 0 8px; font-size: 12px;
  border: 1px dashed var(--px-primary-100); border-radius: 6px; background: var(--px-primary-50);
}
.posx-qty { display: inline-flex; align-items: center; border: 1px solid var(--px-border); border-radius: 8px; overflow: hidden; }
.posx-qty button {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 32px; border: 0; background: var(--px-bg); color: var(--px-text); cursor: pointer;
}
.posx-qty button:hover { background: var(--px-primary-50); color: var(--px-primary); }
.posx-qty .posx-svg { width: 14px; height: 14px; }
.posx-qty input {
  width: 50px; height: 32px; border: 0; text-align: center; font-weight: 600; -moz-appearance: textfield;
}
.posx-qty input::-webkit-outer-spin-button, .posx-qty input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.posx-line-total { text-align: end; font-weight: 700; color: var(--px-primary); white-space: nowrap; }
.posx-line-remove {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border: 0; border-radius: 6px; background: transparent; color: var(--px-muted); cursor: pointer;
}
.posx-line-remove:hover { background: #fee2e2; color: var(--px-danger); }
.posx-line-remove-spacer { width: 28px; }
.posx-line-confirm {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border: 1px solid var(--px-border); border-radius: 8px;
  background: var(--px-surface); color: var(--px-muted); cursor: pointer;
}
.posx-line-confirm .posx-svg { width: 15px; height: 15px; }
.posx-line-confirm:hover:not(:disabled) { border-color: var(--px-success); color: var(--px-success); background: #f0fdf4; }
.posx-line-confirm.is-on { border-color: var(--px-success); background: var(--px-success); color: #fff; }
.posx-line-confirm.is-on:disabled { opacity: 1; cursor: default; }
.posx-line-confirm.is-on:hover:not(:disabled) { background: #15803d; color: #fff; }
.posx-line.is-confirmed { border-color: #bbf7d0; background: #f6fef9; }
.posx-line.is-confirmed .posx-qty button:disabled, .posx-line.is-confirmed input:disabled { opacity: .65; cursor: not-allowed; }
.posx-price input:disabled { background: var(--px-bg); color: var(--px-text); cursor: default; }
.posx-line-remove .posx-svg { width: 15px; height: 15px; }

.posx-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 48px 16px; color: var(--px-muted); text-align: center; }
.posx-empty p { margin: 8px 0 2px; color: var(--px-text); font-weight: 500; }
.posx-empty-icon { width: 40px; height: 40px; color: #c4c4d4; }
.posx-empty-sm { padding: 24px; }

.posx-cart-foot { border-top: 1px solid var(--px-border); padding: 10px 12px; background: #fbfbfd; }
.posx-foot-total { display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 16px; }
.posx-foot-total bdi { color: var(--px-primary); font-size: 18px; }
.posx-adjust { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.posx-adjust label, .posx-form-grid label, .posx-block { display: flex; flex-direction: column; gap: 4px; margin: 0; }
.posx-adjust label > span, .posx-form-grid label > span, .posx-block > span { font-size: 11px; font-weight: 600; color: var(--px-muted); }
.posx-input {
  display: flex; align-items: center; height: 36px; padding: 0 10px; gap: 6px;
  border: 1px solid var(--px-border); border-radius: 8px; background: var(--px-surface);
}
.posx-input:focus-within { border-color: var(--px-primary); box-shadow: 0 0 0 3px var(--px-primary-50); }
.posx-input em { font-style: normal; color: var(--px-muted); font-size: 12px; }
.posx-input input { flex: 1; min-width: 0; border: 0; outline: 0; background: transparent; height: 100%; }
.posx-input-lg { height: 46px; }
.posx-input-lg input { font-size: 18px; font-weight: 700; }
.posx-sum { margin-top: 10px; }
.posx-sum > div { display: flex; justify-content: space-between; padding: 3px 0; color: var(--px-muted); }
.posx-sum > div bdi { color: var(--px-text); font-weight: 600; }

/* ---------- catalog ---------- */
.posx-catalog { flex: 1 1 auto; display: flex; flex-direction: column; min-width: 0; min-height: 0; }
.posx-search-row { display: flex; gap: 8px; padding: 10px 12px; }
.posx-search {
  position: relative; flex: 1; display: flex; align-items: center; height: 42px; padding: 0 12px; gap: 8px;
  background: var(--px-surface); border: 1px solid var(--px-border); border-radius: var(--px-radius);
}
.posx-search:focus-within { border-color: var(--px-primary); box-shadow: 0 0 0 3px var(--px-primary-50); }
.posx-search-icon { color: var(--px-muted); }
.posx-search input { flex: 1; border: 0; outline: 0; height: 100%; background: transparent; min-width: 0; }
.posx-search-clear { border: 0; background: transparent; color: var(--px-muted); cursor: pointer; padding: 4px; display: inline-flex; }
.posx-search-clear .posx-svg { width: 15px; height: 15px; }
.posx-btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  height: 42px; padding: 0 14px; white-space: nowrap;
  border: 1px solid var(--px-border); border-radius: var(--px-radius); background: var(--px-surface); color: var(--px-text);
  font-weight: 500; cursor: pointer;
}
.posx-btn:hover:not(:disabled) { border-color: var(--px-primary-100); background: var(--px-primary-50); color: var(--px-primary); }
.posx-btn:disabled { opacity: .5; cursor: not-allowed; }
.posx-btn-sm { height: 32px; padding: 0 10px; font-size: 13px; }
.posx-btn-primary { background: var(--px-primary); border-color: var(--px-primary); color: #fff; }
.posx-btn-primary:hover:not(:disabled) { background: var(--px-primary-600); color: #fff; }
.posx-grid-wrap { position: relative; flex: 1 1 auto; overflow-y: auto; padding: 0 12px 12px; }
.posx-grid-loading { position: absolute; inset: 0; display: flex; align-items: flex-start; justify-content: center; padding-top: 40px; background: rgba(245, 245, 249, .6); z-index: 2; }
.posx-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; }
.posx-card {
  position: relative; display: flex; flex-direction: column; gap: 4px; padding: 10px; text-align: start;
  background: var(--px-surface); border: 1px solid var(--px-border); border-radius: var(--px-radius); cursor: pointer;
  transition: border-color .15s, box-shadow .15s, transform .05s;
}
.posx-card:hover { border-color: var(--px-primary-100); box-shadow: 0 4px 14px rgba(102, 51, 153, .08); }
.posx-card:active { transform: scale(.99); }
.posx-card.is-out { opacity: .55; }
.posx-card.is-busy { opacity: .7; pointer-events: none; }
.posx-card-img { position: relative; display: flex; align-items: center; justify-content: center; height: 86px; border-radius: 8px; background: #fafafc; overflow: hidden; }
.posx-card-img img { max-height: 86px; max-width: 100%; object-fit: contain; }
.posx-card-noimg { width: 34px; height: 34px; color: #cfcfdc; }
.posx-card-incart {
  position: absolute; top: 6px; inset-inline-end: 6px; padding: 1px 7px; border-radius: 999px;
  background: var(--px-primary); color: #fff; font-size: 11px; font-weight: 700;
}
.posx-card-info {
  position: absolute; top: 6px; inset-inline-start: 6px;
  display: inline-flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; border-radius: 50%;
  background: rgba(255, 255, 255, .92); color: var(--px-muted); box-shadow: 0 1px 3px rgba(0, 0, 0, .12);
}
.posx-card-info .posx-svg { width: 16px; height: 16px; }
.posx-card-info:hover, .posx-card-info:focus { color: #fff; background: var(--px-primary); outline: 0; }

/* product info dialog */
.posx-info-search { margin-bottom: 12px; }
.posx-info-hint { text-align: center; padding: 18px 0; margin: 0; }
.posx-info-results { display: flex; flex-direction: column; gap: 6px; }
.posx-info-result {
  display: flex; align-items: center; gap: 10px; width: 100%; padding: 8px 10px; text-align: start;
  border: 1px solid var(--px-border); border-radius: var(--px-radius); background: var(--px-surface); cursor: pointer;
}
.posx-info-result:hover { border-color: var(--px-primary-100); background: var(--px-primary-50); }
.posx-info-thumb, .posx-info-img {
  display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto;
  width: 44px; height: 44px; border-radius: 8px; background: #fafafc; color: #cfcfdc; overflow: hidden;
}
.posx-info-thumb img, .posx-info-img img { max-width: 100%; max-height: 100%; object-fit: contain; }
.posx-info-result-main { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.posx-info-result-main strong { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.posx-info-result-main small { color: var(--px-muted); }
.posx-info-result-side { display: flex; flex-direction: column; align-items: flex-end; font-size: 12px; white-space: nowrap; }
.posx-link {
  display: inline-flex; align-items: center; gap: 4px; padding: 0; margin-bottom: 10px;
  border: 0; background: transparent; color: var(--px-primary); font-weight: 600; cursor: pointer;
}
.posx-link .posx-svg { width: 16px; height: 16px; }
.posx-info-head { display: flex; gap: 14px; align-items: flex-start; margin-bottom: 14px; }
.posx-info-img { width: 84px; height: 84px; border: 1px solid var(--px-border); }
.posx-info-img .posx-svg { width: 34px; height: 34px; }
.posx-info-title { flex: 1; min-width: 0; }
.posx-info-title h5 { margin: 0 0 2px; font-weight: 700; font-size: 17px; }
.posx-info-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
.posx-info-tags span { padding: 2px 8px; border-radius: 999px; background: var(--px-bg); font-size: 11px; font-weight: 600; color: var(--px-muted); }
.posx-info-tags .is-accent { background: var(--px-primary-50); color: var(--px-primary); }
.posx-info-tags .is-danger { background: #fee2e2; color: #b91c1c; }
.posx-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; margin-bottom: 16px; }
.posx-kpis > div { display: flex; flex-direction: column; gap: 2px; padding: 10px 12px; border-radius: var(--px-radius); background: var(--px-bg); }
.posx-kpis small { font-size: 11px; font-weight: 600; color: var(--px-muted); text-transform: uppercase; letter-spacing: .03em; }
.posx-kpis strong { font-size: 17px; }
.posx-kpis em { display: block; font-style: normal; font-size: 12px; font-weight: 600; }
.posx-info-section { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--px-muted); margin: 0 0 6px; }
.posx-here { margin-inline-start: 4px; padding: 1px 6px; border-radius: 6px; background: var(--px-primary); color: #fff; font-size: 10px; font-weight: 600; }
.posx-info-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px 16px; margin-top: 16px; }
.posx-info-grid > div { display: flex; flex-direction: column; gap: 2px; min-width: 0; }
.posx-info-grid span { font-size: 11px; font-weight: 600; color: var(--px-muted); }
.posx-info-grid strong { font-weight: 600; overflow-wrap: anywhere; }
.posx-info-wide { grid-column: 1 / -1; }
.posx-text-success { color: var(--px-success); }
@media (max-width: 575px) {
  .posx-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .posx-info-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .posx-search-row .posx-btn span { display: none; }
}
.posx-card-name { font-weight: 600; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.posx-card-meta { color: var(--px-muted); font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.posx-card-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 2px; }
.posx-card-price { color: var(--px-primary); font-weight: 700; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.posx-card-add {
  display: inline-flex; align-items: center; justify-content: center;
  width: 26px; height: 26px; border-radius: 7px; background: var(--px-primary-50); color: var(--px-primary);
}
.posx-card-add .posx-svg { width: 15px; height: 15px; }
.posx-card:hover .posx-card-add { background: var(--px-primary); color: #fff; }

.posx-pager { display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-top: 1px solid var(--px-border); background: var(--px-surface); }
.posx-pager-info { flex: 1; text-align: center; font-size: 12px; line-height: 1.2; display: flex; flex-direction: column; }
.posx-pager-info small { color: var(--px-muted); }
.posx-pager-pages { display: flex; gap: 4px; align-items: center; }
.posx-pager-pages button {
  min-width: 30px; height: 30px; padding: 0 6px; border: 1px solid var(--px-border); border-radius: 7px;
  background: var(--px-surface); font-size: 12px; cursor: pointer;
}
.posx-pager-pages button.active { background: var(--px-primary); border-color: var(--px-primary); color: #fff; }
.posx-pager-gap { color: var(--px-muted); padding: 0 2px; }
.posx-pager-modal { border-top: 0; padding: 12px 0 0; }

/* ---------- bottom bar ---------- */
.posx-bottom {
  display: flex; align-items: center; gap: 8px; padding: 8px 12px;
  background: var(--px-surface); border-top: 1px solid var(--px-border); flex-wrap: wrap;
}
.posx-status { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; padding-inline-end: 8px; border-inline-end: 1px solid var(--px-border); }
.posx-status i { width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
.posx-status.is-online { color: var(--px-success); }
.posx-status.is-offline { color: var(--px-danger); }
.posx-status.is-saving { color: #d97706; }
.posx-status small { color: var(--px-muted); }
.posx-payable { display: flex; flex-direction: column; align-items: flex-end; line-height: 1.1; padding-inline-end: 6px; }
.posx-payable small { font-size: 10px; text-transform: uppercase; color: var(--px-muted); letter-spacing: .05em; font-weight: 600; }
.posx-payable strong { font-size: 22px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
.posx-pay {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  height: 46px; padding: 0 26px; border: 0; border-radius: var(--px-radius);
  background: var(--px-primary); color: #fff; font-weight: 700; font-size: 15px; cursor: pointer;
}
.posx-pay:hover:not(:disabled) { background: var(--px-primary-600); }
.posx-pay:disabled { opacity: .45; cursor: not-allowed; }
.posx-confirm {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  height: 46px; padding: 0 18px; border: 1.5px solid var(--px-primary); border-radius: var(--px-radius);
  background: var(--px-surface); color: var(--px-primary); font-weight: 700; font-size: 15px; cursor: pointer; white-space: nowrap;
}
.posx-confirm:hover:not(:disabled) { background: var(--px-primary-50); }
.posx-confirm:disabled { opacity: .45; cursor: not-allowed; }

/* ---------- modal content (rendered inside the component) ---------- */
.posx-pay-due { display: flex; flex-direction: column; align-items: center; padding: 14px; margin-bottom: 14px; border-radius: var(--px-radius); background: var(--px-primary-50); }
.posx-pay-due span { font-size: 12px; text-transform: uppercase; color: var(--px-primary); font-weight: 600; letter-spacing: .04em; }
.posx-pay-due strong { font-size: 28px; color: var(--px-primary); }
.posx-pay-due small { color: var(--px-muted); }
.posx-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; margin-bottom: 10px; }
.posx-quick { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.posx-quick button {
  height: 32px; padding: 0 12px; border: 1px solid var(--px-border); border-radius: 999px; background: var(--px-surface);
  font-size: 12px; font-weight: 600; cursor: pointer;
}
.posx-quick button:hover { border-color: var(--px-primary); color: var(--px-primary); }
.posx-pay-change { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px; }
.posx-pay-change > div { display: flex; flex-direction: column; padding: 10px 12px; border-radius: var(--px-radius); background: #f0fdf4; }
.posx-pay-change > div span { font-size: 11px; color: var(--px-muted); font-weight: 600; }
.posx-pay-change > div strong { font-size: 18px; color: var(--px-success); }
.posx-pay-change > div:last-child { background: var(--px-bg); }
.posx-pay-change > div:last-child strong { color: var(--px-text); }
.posx-pay-change.is-due > div:last-child { background: #fef2f2; }
.posx-pay-change.is-due > div:last-child strong { color: var(--px-danger); }
.posx-select, .posx-field, .posx-textarea {
  width: 100%; border: 1px solid var(--px-border); border-radius: 8px; padding: 0 10px; background: var(--px-surface); outline: 0;
}
.posx-select, .posx-field { height: 38px; }
.posx-textarea { padding: 8px 10px; resize: vertical; }
.posx-select:focus, .posx-field:focus, .posx-textarea:focus { border-color: var(--px-primary); box-shadow: 0 0 0 3px var(--px-primary-50); }
.posx-block { margin-bottom: 10px !important; }
.posx-modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 14px; }
.posx-modal-actions-stack { flex-direction: column; align-items: stretch; }
.posx-print-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
.posx-section-title { margin: 16px 0 8px; font-size: 12px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--px-muted); }
.posx-form-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.posx-due-note { align-self: center; color: var(--px-danger); font-weight: 700; font-size: 13px; margin-inline-start: 4px; }
.posx-seg { display: inline-flex; padding: 3px; gap: 3px; border-radius: 10px; background: var(--px-bg); margin-bottom: 10px; }
.posx-seg button { height: 32px; padding: 0 14px; border: 0; border-radius: 8px; background: transparent; color: var(--px-muted); font-weight: 600; cursor: pointer; }
.posx-seg button.active { background: var(--px-surface); color: var(--px-primary); box-shadow: 0 1px 3px rgba(0, 0, 0, .08); }
.posx-hint { font-size: 12px; margin: 0 0 8px; }
.posx-charges { display: flex; flex-direction: column; gap: 8px; }
.posx-charge-row { display: grid; grid-template-columns: 130px 170px minmax(0, 1fr) 32px; gap: 8px; align-items: center; }
.posx-charge-row.is-single { grid-template-columns: 130px 170px minmax(0, 1fr); }
.posx-charges .posx-link { align-self: flex-start; margin: 0; }
.posx-charges .posx-sum { margin-top: 2px; padding-top: 6px; border-top: 1px dashed var(--px-border); }
.posx-mt { margin-top: 14px; }
.posx-day-bar { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.posx-day-nav, .posx-day-print { display: flex; align-items: center; gap: 6px; }
.posx-day-date { width: 160px; }
.posx-day-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px; margin-bottom: 12px; }
.posx-day-kpis > div { display: flex; flex-direction: column; padding: 8px 12px; border-radius: var(--px-radius); background: var(--px-bg); }
.posx-day-kpis small { font-size: 11px; font-weight: 600; color: var(--px-muted); text-transform: uppercase; }
.posx-day-kpis strong { font-size: 16px; }
.posx-day-name { font-weight: 600; white-space: normal; }
.posx-day .posx-table td { white-space: normal; }
.posx-day .posx-table td.posx-num, .posx-day .posx-table td.posx-mono { white-space: nowrap; }
.posx-day-charges { margin-top: 10px; }
.posx-row-between { display: flex; justify-content: space-between; gap: 10px; padding: 4px 0; }
.posx-done { text-align: center; }
.posx-done .posx-sum { text-align: start; margin: 12px 0; }
.posx-done-icon {
  display: inline-flex; align-items: center; justify-content: center;
  width: 56px; height: 56px; border-radius: 50%; background: #dcfce7; color: var(--px-success);
}
.posx-done-icon .posx-svg { width: 28px; height: 28px; }
.posx-tabs { display: flex; gap: 6px; margin-bottom: 10px; }
.posx-tabs button {
  height: 34px; padding: 0 14px; border: 1px solid var(--px-border); border-radius: 8px; background: var(--px-surface); font-weight: 600; cursor: pointer;
}
.posx-tabs button.active { background: var(--px-primary); border-color: var(--px-primary); color: #fff; }
.posx-table-wrap { overflow-x: auto; }
.posx-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.posx-table th { text-align: start; font-size: 11px; text-transform: uppercase; color: var(--px-muted); font-weight: 600; padding: 8px; border-bottom: 1px solid var(--px-border); white-space: nowrap; }
.posx-table td { padding: 8px; border-bottom: 1px solid var(--px-border); white-space: nowrap; }
.posx-table tr.is-link { cursor: pointer; }
.posx-table tr.is-link:hover td, .posx-table tr.is-current td { background: var(--px-primary-50); }
.posx-num { text-align: end !important; }
.posx-actions { display: flex; gap: 6px; justify-content: flex-end; align-items: center; }
.posx-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; background: var(--px-bg); }
.posx-badge.is-paid { background: #dcfce7; color: #15803d; }
.posx-badge.is-partial { background: #fef3c7; color: #b45309; }
.posx-badge.is-unpaid { background: #fee2e2; color: #b91c1c; }
.posx-scanner video { width: 100%; max-height: 320px; border-radius: var(--px-radius); background: #000; }
.posx-scanner p { margin: 10px 0 0; text-align: center; }

/* ---------- responsive ---------- */
@media (max-width: 1199px) {
  .posx-hide-sm { display: none; }
  .posx-customer { min-width: 190px; }
  .posx-vselect { min-width: 130px; }
}
@media (max-width: 991px) {
  .posx { position: static; min-height: 100vh; }
  .posx-main { flex-direction: column; }
  .posx-cart { flex: none; max-width: none; border-inline-end: 0; border-bottom: 1px solid var(--px-border); }
  .posx-cart-lines { max-height: 45vh; }
  .posx-grid-wrap { overflow: visible; }
  .posx-bottom { position: sticky; bottom: 0; z-index: 5; }
}
@media (max-width: 575px) {
  .posx-top { gap: 6px; padding: 8px; }
  .posx-brand-title { display: none; }
  .posx-customer { flex: 1 1 100%; order: 10; }
  .posx-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .posx-line { grid-template-columns: 80px auto minmax(0, 1fr) 30px 28px; }
  .posx-line-main { grid-column: 1 / -1; }
  .posx-line-total { text-align: end; }
  .posx-form-grid-3, .posx-charge-row, .posx-charge-row.is-single { grid-template-columns: 1fr; }
  .posx-adjust, .posx-form-grid { grid-template-columns: 1fr; }
  .posx-bottom .posx-btn span, .posx-confirm span { display: none; }
  .posx-confirm, .posx-pay { padding: 0 14px; }
  .posx-pager-pages { display: none; }
}
</style>

<style>
/* theme tokens: shared by the page and its dialogs (dialogs are rendered at <body> level) */
.posx,
.posx-modal {
  --px-primary: #663399;
  --px-primary-600: #57288a;
  --px-primary-50: #f3edfa;
  --px-primary-100: #e7dcf5;
  --px-bg: #f5f5f9;
  --px-surface: #ffffff;
  --px-border: #e6e6ef;
  --px-text: #1f1f2e;
  --px-muted: #74748a;
  --px-success: #16a34a;
  --px-danger: #dc2626;
  --px-radius: 10px;
}
.posx-modal { border: 0; border-radius: 14px; }
.posx-dialog-scroll .modal-content { max-height: calc(100vh - 2rem); overflow: hidden; display: flex; flex-direction: column; }
.posx-dialog-scroll .modal-body { overflow-y: auto; -webkit-overflow-scrolling: touch; }
.posx-dialog-scroll.modal-dialog-centered { min-height: calc(100% - 2rem); margin-top: 1rem; margin-bottom: 1rem; }
.posx-modal [dir] { text-align: start; }
.posx-modal-rtl .modal-header { flex-direction: row-reverse; }
.posx-modal-rtl .modal-header .close { margin: -1rem auto -1rem -1rem; }
.posx-modal-rtl .modal-title { text-align: right; }
/* arrows point the other way in Arabic, also inside the dialogs (rendered outside the page) */
.posx-modal-rtl .posx-flip { transform: scaleX(-1); }
.posx-dialog-sm { max-width: 400px; }
.posx-dialog-md { max-width: 560px; }
.posx-dialog-lg { max-width: 860px; }
</style>
