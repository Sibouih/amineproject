<template>
  <div class="main-content">
    <breadcumb :page="$t('AddProductExchange')" :folder="$t('ListExchanges')" />
    <div
      v-if="isLoading"
      class="loading_page spinner spinner-primary mr-3"
    ></div>
    <div v-else>
      <b-form @submit.prevent="Submit_Exchange">
        <b-row>
          <b-col lg="12" md="12" sm="12">
            <b-card>
              <b-row>
                <!-- Common Fields -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider
                    name="date"
                    :rules="{ required: true }"
                    v-slot="validationContext"
                  >
                    <b-form-group :label="$t('date') + ' ' + '*'">
                      <b-form-input
                        :state="getValidationState(validationContext)"
                        type="date"
                        v-model="exchange.date"
                      ></b-form-input>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Products Section -->
                <b-col md="12" class="mb-5">
                  <b-row>
                    <!-- Purchase Products -->
                    <b-col md="6">
                      <div class="purchase-section">
                        <div class="d-flex align-items-center mb-3">
                          <validation-provider
                            name="Customer"
                            :rules="{ required: true }"
                            class="flex-grow-1 mr-2"
                          >
                            <b-form-group :label="$t('Customer') + ' ' + '*'">
                              <v-select
                                v-model="exchange.customer_id"
                                :reduce="(label) => label.value"
                                :placeholder="$t('Choose_Customer')"
                                :options="
                                  clients.map((clients) => ({
                                    label: clients.name,
                                    value: clients.id,
                                  }))
                                "
                              />
                            </b-form-group>
                          </validation-provider>

                          <b-button
                            variant="info"
                            size="sm"
                            @click="New_Client"
                            class="mt-4"
                          >
                            <i class="i-Add"></i>
                          </b-button>
                        </div>
                        <h5>{{ $t("Products_to_Purchase") }}</h5>
                        <!-- Search Input -->
                        <div class="d-flex align-items-center mb-3">
                          <div class="flex-grow-1 mr-2">
                            <div id="autocomplete" class="autocomplete">
                              <input
                                :placeholder="$t('Scan_Search_Product_by_Code_Name')"
                                @input='e => search_input = e.target.value'
                                @keyup="search(search_input)"
                                @focus="handleFocus"
                                @blur="handleBlur"
                                ref="product_autocomplete"
                                class="autocomplete-input form-control"
                              />
                              <ul
                                class="autocomplete-result-list"
                                v-show="focused"
                              >
                                <li
                                  class="autocomplete-result"
                                  v-for="product_fil in product_filter"
                                  @mousedown="SearchProduct(product_fil)"
                                >
                                  {{ getResultValue(product_fil) }}
                                </li>
                              </ul>
                            </div>
                          </div>

                          <!-- Add New Product Button -->
                          <div>
                            <b-button variant="primary" @click="showNewProductModal">
                              <i class="i-Add"></i> {{$t('AddNewProduct')}}
                            </b-button>
                          </div>
                        </div>

                        <!-- Purchase Products Table -->
                        <div class="table-responsive">
                          <table class="table table-hover" id="products-table">
                            <thead>
                              <tr>
                                <th>{{ $t("Product") }}</th>
                                <th>{{ $t("Qty") }}</th>
                                <th>{{ $t("Price") }}</th>
                                <th>{{ $t("SubTotal") }}</th>
                                <th>{{ $t("Action") }}</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr v-for="item in purchase_details" :key="item.id">
                                <td>{{ item.name }}</td>
                                <td>
                                  <div class="quantity">
                                    <b-input-group>
                                      <b-input-group-prepend>
                                        <span
                                          class="btn btn-primary btn-sm"
                                          @click="decrementPurchase(item)"
                                          >-</span
                                        >
                                      </b-input-group-prepend>
                                      <input
                                        class="form-control"
                                        @keyup="
                                          Verified_Qty(detail, detail.detail_id)
                                        "
                                        :min="0.0"
                                        v-model.number="item.quantity"
                                      />
                                      <b-input-group-append>
                                        <span
                                          class="btn btn-primary btn-sm"
                                          @click="incrementPurchase(item)"
                                          >+</span
                                        >
                                      </b-input-group-append>
                                    </b-input-group>
                                  </div>
                                </td>
                                <td>
                                  {{ formatNumber(item.Unit_cost || 0, 2) }}
                                </td>
                                <td>
                                  {{ formatNumber(item.subtotal || 0, 2) }}
                                </td>
                                <td>
                                  <i
                                    class="i-Close-Window text-25 text-danger cursor-pointer"
                                    @click="removePurchaseItem(item)"
                                  ></i>
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </b-col>

                    <!-- Sale Products -->
                    <b-col md="6">
                      <div class="sale-section">
                        <div class="d-flex align-items-center mb-3">
                          <validation-provider
                            name="Supplier"
                            :rules="{ required: true }"
                            class="flex-grow-1 mr-2"
                          >
                            <b-form-group :label="$t('Supplier') + ' ' + '*'">
                              <v-select
                                v-model="exchange.supplier_id"
                                :reduce="(label) => label.value"
                                :placeholder="$t('Choose_Supplier')"
                                :options="
                                  suppliers.map((suppliers) => ({
                                    label: suppliers.name,
                                    value: suppliers.id,
                                  }))
                                "
                              />
                            </b-form-group>
                          </validation-provider>

                          <b-button
                            variant="info"
                            size="sm"
                            @click="New_Supplier"
                            class="mt-4"
                          >
                            <i class="i-Add"></i>
                          </b-button>
                        </div>
                        <h5>{{ $t("Products_to_Sell") }}</h5>
                        <div class="search-box mb-3">
                          <div id="sale_autocomplete" class="autocomplete">
                            <input
                              :placeholder="$t('Scan_Search_Product_by_Code_Name')"
                              v-model="sale_search"
                              @keyup="searchSale"
                              @focus="handleSaleFocus"
                              @blur="handleSaleBlur"
                              ref="sale_product_autocomplete"
                              class="autocomplete-input form-control"
                            />
                            <ul
                              class="autocomplete-result-list"
                              v-show="sale_focused"
                            >
                              <li
                                class="autocomplete-result"
                                v-for="product_fil in sale_products_filter"
                                :key="product_fil.id"
                                @mousedown="SearchSaleProduct(product_fil)"
                              >
                                {{ getResultValue(product_fil) }}
                              </li>
                            </ul>
                          </div>
                        </div>

                        <!-- Sale Products Table -->
                        <div class="table-responsive">
                          <table class="table table-hover" id="sales-products-table">
                            <thead>
                              <tr>
                                <th>{{ $t("Product") }}</th>
                                <th>{{ $t("Qty") }}</th>
                                <th>{{ $t("Price") }}</th>
                                <th>{{ $t("SubTotal") }}</th>
                                <th>{{ $t("Action") }}</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr v-for="item in sale_details" :key="item.id">
                                <td>{{ item.name }}</td>
                                <td>
                                  <div class="quantity">
                                    <b-input-group>
                                      <b-input-group-prepend>
                                        <span
                                          class="btn btn-primary btn-sm"
                                          @click="decrementSale(item)"
                                          >-</span
                                        >
                                      </b-input-group-prepend>
                                      <input
                                        class="form-control"
                                        @keyup="
                                          Verified_Sale_Qty(detail, detail.detail_id)
                                        "
                                        :min="0.0"
                                        v-model.number="item.quantity"
                                      />
                                      <b-input-group-append>
                                        <span
                                          class="btn btn-primary btn-sm"
                                          @click="incrementSale(item)"
                                          >+</span
                                        >
                                      </b-input-group-append>
                                    </b-input-group>
                                  </div>
                                </td>                                
                                <td>
                                  {{ formatNumber(item.Unit_cost || 0, 2) }}
                                </td>
                                <td>
                                  {{ formatNumber(item.subtotal || 0, 2) }}
                                </td>
                                <td>
                                  <i
                                    class="i-Close-Window text-25 text-danger cursor-pointer"
                                    @click="removeSaleItem(item)"
                                  ></i>
                                </td>
                              </tr>
                            </tbody>
                          </table>
                        </div>
                      </div>
                    </b-col>
                  </b-row>
                </b-col>

                <!-- Totals Section -->
                <b-col md="12">
                  <div class="d-flex justify-content-between">
                    <div class="purchase-total">
                      <h6>
                        {{ $t("Purchase_Total") }}:
                        {{ formatNumber(purchase_total) }}
                      </h6>
                    </div>
                    <div class="sale-total">
                      <h6>
                        {{ $t("Sale_Total") }}: {{ formatNumber(sale_total) }}
                      </h6>
                    </div>
                  </div>
                  <div class="text-right">
                    <h5>
                      {{ $t("Différence") }}:
                      {{ formatNumber(sale_total - purchase_total) }}
                    </h5>
                  </div>
                </b-col>

                <!-- Submit Button -->
                <b-col md="12">
                  <b-button
                    variant="primary"
                    @click="Submit_Exchange"
                    :disabled="SubmitProcessing"
                  >
                    <i class="i-Yes me-2 font-weight-bold"></i>
                    {{ $t("submit") }}
                  </b-button>
                </b-col>
              </b-row>
            </b-card>
          </b-col>
        </b-row>
      </b-form>
    </div>

    <!-- Modal Create Customer -->
    <validation-observer ref="Create_Customer">
      <b-modal hide-footer size="lg" id="New_Customer" :title="$t('Add')">
        <b-form @submit.prevent="Submit_Customer">
          <div class="row">
            <!-- Client Name -->
            <div class="col-sm-12 col-md-6">
              <validation-provider
                name="Name Client"
                :rules="{ required: true }"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('CustomerName') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="name-feedback"
                    :placeholder="$t('CustomerName')"
                    v-model="client.name"
                    label="name"
                  ></b-form-input>
                  <b-form-invalid-feedback id="name-feedback">
                    {{ validationContext.errors[0] }}
                  </b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </div>

            <!-- Phone -->
            <div class="col-sm-12 col-md-6">
              <b-form-group :label="$t('Phone')">
                <b-form-input
                  v-model="client.phone"
                  :placeholder="$t('Phone')"
                  label="Phone"
                ></b-form-input>
              </b-form-group>
            </div>

            <!-- Submit Button -->
            <div class="col-md-12 mt-3">
              <b-button
                variant="primary"
                type="submit"
                :disabled="SubmitProcessing"
              >
                {{ $t("submit") }}
              </b-button>
              <div
                v-if="SubmitProcessing"
                class="spinner spinner-primary mt-3"
              ></div>
            </div>
          </div>
        </b-form>
      </b-modal>
    </validation-observer>

    <!-- Modal Create Supplier -->
    <validation-observer ref="Create_Supplier">
      <b-modal hide-footer size="lg" id="New_Supplier" :title="$t('Add')">
        <b-form @submit.prevent="Submit_Supplier">
          <div class="row">
            <!-- Supplier Name -->
            <div class="col-sm-12 col-md-6">
              <validation-provider
                name="Name Supplier"
                :rules="{ required: true }"
                v-slot="validationContext"
              >
                <b-form-group :label="$t('SupplierName') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="name-feedback"
                    :placeholder="$t('SupplierName')"
                    v-model="supplier.name"
                    label="name"
                  ></b-form-input>
                  <b-form-invalid-feedback id="name-feedback">
                    {{ validationContext.errors[0] }}
                  </b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </div>

            <!-- Phone -->
            <div class="col-sm-12 col-md-6">
              <b-form-group :label="$t('Phone')">
                <b-form-input
                  v-model="supplier.phone"
                  :placeholder="$t('Phone')"
                  label="Phone"
                ></b-form-input>
              </b-form-group>
            </div>

            <!-- Submit Button -->
            <div class="col-md-12 mt-3">
              <b-button
                variant="primary"
                type="submit"
                :disabled="SubmitProcessing"
              >
                {{ $t("submit") }}
              </b-button>
              <div
                v-if="SubmitProcessing"
                class="spinner spinner-primary mt-3"
              ></div>
            </div>
          </div>
        </b-form>
      </b-modal>
    </validation-observer>

    <!-- Add the New Product Modal -->
    <validation-observer ref="Create_Product">
      <b-modal id="New_Product" :title="$t('AddNewProduct')" size="lg" hide-footer>
        <b-form @submit.prevent="Submit_Product" enctype="multipart/form-data">
          <b-row>
            <!-- Name -->
            <b-col md="6" class="mb-2">
              <validation-provider name="Name" :rules="{required:true, min:3, max:55}" v-slot="validationContext">
                <b-form-group :label="$t('Name_product') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    aria-describedby="Name-feedback"
                    label="Name"
                    :placeholder="$t('Enter_Name_Product')"
                    v-model="new_product.name"
                  ></b-form-input>
                  <b-form-invalid-feedback id="Name-feedback">{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>
            
            <!-- Code Product -->
            <b-col md="6" class="mb-2">
              <validation-provider name="Code Product" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('CodeProduct') + ' ' + '*'">
                  <div class="input-group">
                    <b-form-input
                      :class="{'is-invalid': !!errors.length}"
                      :state="errors[0] ? false : (valid ? true : null)"
                      v-model="new_product.code"
                    ></b-form-input>
                    <div class="input-group-append">
                      <span class="input-group-text">
                        <a @click="generateNumber()"><i class="i-Bar-Code cursor-pointer"></i></a>
                      </span>
                    </div>
                    <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                  </div>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Category -->
            <b-col md="6" class="mb-2">
              <validation-provider name="category" :rules="{ required: true}">
                <b-form-group slot-scope="{ valid, errors }" :label="$t('Categorie') + ' ' + '*'">
                  <v-select
                    :class="{'is-invalid': !!errors.length}"
                    :state="errors[0] ? false : (valid ? true : null)"
                    v-model="new_product.category_id"
                    :reduce="label => label.value"
                    :placeholder="$t('Choose_Category')"
                    :options="categories.map(categories => ({label: categories.name, value: categories.id}))"
                  />
                  <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Brand -->
            <b-col md="6" class="mb-2">
              <b-form-group :label="$t('Brand')">
                <v-select
                  v-model="new_product.brand_id"
                  :reduce="label => label.value"
                  :placeholder="$t('Choose_Brand')"
                  :options="brands.map(brands => ({label: brands.name, value: brands.id}))"
                />
              </b-form-group>
            </b-col>

            <!-- Cost -->
            <b-col md="6" class="mb-2">
              <validation-provider name="Cost" :rules="{ required: true, regex: /^\d*\.?\d*$/}" v-slot="validationContext">
                <b-form-group :label="$t('ProductCost') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    v-model.number="new_product.cost"
                  ></b-form-input>
                  <b-form-invalid-feedback>{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>

            <!-- Price -->
            <b-col md="6" class="mb-2">
              <validation-provider name="Price" :rules="{ required: true, regex: /^\d*\.?\d*$/}" v-slot="validationContext">
                <b-form-group :label="$t('ProductPrice') + ' ' + '*'">
                  <b-form-input
                    :state="getValidationState(validationContext)"
                    v-model.number="new_product.price"
                  ></b-form-input>
                  <b-form-invalid-feedback>{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                </b-form-group>
              </validation-provider>
            </b-col>                                    

            <b-col md="12" class="mt-3">
              <b-button variant="primary" type="submit" :disabled="SubmitProcessing">
                {{ $t('submit') }}
              </b-button>
              <b-button variant="secondary" @click="hideNewProductModal">
                {{ $t('Cancel') }}
              </b-button>
            </b-col>
          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  data() {
    return {
      isLoading: true,

      focused: false,
      timer: null,
      search_input: "",
      search_input_sale: "",
      product_filter: [],

      // Search states
      purchase_search: "",
      sale_search: "",
      purchase_focused: false,
      sale_focused: false,
      purchase_products_filter: [],
      sale_products_filter: [],

      sale_search: "",
      sale_focused: false,
      sale_products_filter: [],

      // Lists and details
      clients: [],
      suppliers: [],
      brands: [],
      categories: [],
      products: [],
      purchase_details: [],
      sale_details: [],

      total: 0,
      GrandPurchaseTotal: 0,
      GrandSaleTotal: 0,

      exchange: {
        date: new Date().toISOString().slice(0,10),
        customer_id: null,
        supplier_id: null,
        warehouse_id: null,
        status: 'completed',
        notes: '',
        tax_rate: 0,
        TaxNet: 0,
        discount: 0,
        shipping: 0
      },

      detail: {
        quantity: "",
        discount: "",
        Unit_cost: "",
        discount_Method: "",
        tax_percent: "",
        tax_method: "",
        imei_number: "",
      },

      // Totals
      purchase_total: 0,
      sale_total: 0,
      client: {
        id: "",
        name: "",
        phone: "",
        email: "",
        country: "",
        city: "",
        adresse: "",
        tax_number: "",
        credit_initial: 0,
      },
      supplier: {
        id: "",
        name: "",
        phone: "",
        email: "",
        country: "",
        city: "",
        adresse: "",
        tax_number: "",
        credit_initial: 0,
      },
      SubmitProcessing: false,

      purchase_product: {
        id: "",
        code: "",
        stock: "",
        quantity: 1,
        discount: "",
        DiscountNet: "",
        discount_Method: "",
        name: "",
        unitPurchase: "",
        purchase_unit_id:"",
        fix_stock:"",
        fix_cost:"",
        Net_cost: "",
        Unit_cost: "",
        Total_cost: "",
        subtotal: "",
        product_id: "",
        detail_id: "",
        taxe: "",
        tax_percent: "",
        tax_method: "",
        product_variant_id: "",
        is_imei: "",
        imei_number:"",
      },

      sale_product: {
        id: "",
        product_type: "",
        code: "",
        stock: "",
        quantity: 1,
        discount: "",
        DiscountNet: "",
        discount_Method: "",
        name: "",
        sale_unit_id:"",
        fix_stock:"",
        fix_price:"",
        unitSale: "",
        Net_price: "",
        Unit_price: "",
        Total_price: "",
        subtotal: "",
        product_id: "",
        detail_id: "",
        taxe: "",
        tax_percent: "",
        tax_method: "",
        product_variant_id: "",
        is_imei: "",
        imei_number:"",
      },

      new_product: {
        type: "is_single",
        name: "",
        code: "",
        Type_barcode: "CODE128",
        cost: "",
        price: "",
        TaxNet: "0",
        tax_method: "1",
        unit_id: 1,
        unit_sale_id: 1,
        unit_purchase_id: 1,
        stock_alert: "0",
        note: "",
        is_variant: false,
        is_imei: false,
        not_selling: false
      },
    };
  },

  computed: {
    ...mapGetters(["currentUser", "currentUserPermissions"]),
  },

  methods: {
    async getInitialData() {
      try {
        const response = await axios.get("exchange/create");
        this.clients = response.data.customers;
        this.suppliers = response.data.suppliers;
        this.brands = response.data.brands;
        this.categories = response.data.categories;
        this.isLoading = false;
      } catch (error) {
        this.isLoading = false;
        this.makeToast(
          "danger",
          this.$t("Error_loading_data"),
          this.$t("Error")
        );
      }
    },

    Get_Products_By_Warehouse(id) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      axios
        .get(
          "get_Products_by_warehouse/" +
            id +
            "?stock=" +
            0 +
            "&product_service=" +
            0
        )
        .then((response) => {
          this.products = response.data;
          NProgress.done();
        })
        .catch((error) => {});
    },

    // Search Products
    searchPurchase() {
      if (this.timer) {
        clearTimeout(this.timer);
        this.timer = null;
      }

      if (this.search_input.length < 2) {
        this.product_filter = [];
        return;
      }

      this.timer = setTimeout(() => {
        const product_filter = this.products.filter(
          (product) =>
            product.code === this.search_input ||
            product.barcode.includes(this.search_input)
        );
        if (product_filter.length === 1) {
          this.SearchProduct(product_filter[0]);
        } else {
          this.product_filter = this.products.filter((product) => {
            return (
              product.name
                .toLowerCase()
                .includes(this.search_input.toLowerCase()) ||
              product.code
                .toLowerCase()
                .includes(this.search_input.toLowerCase()) ||
              product.barcode
                .toLowerCase()
                .includes(this.search_input.toLowerCase())
            );
          });
        }
      }, 800);
    },

    searchSale() {
      if (this.timer) {
        clearTimeout(this.timer);
        this.timer = null;
      }

      if (this.sale_search.length < 2) {
        this.sale_products_filter = [];
        return;
      }

      this.timer = setTimeout(() => {
        const product_filter = this.products.filter(
          (product) =>
            product.code === this.sale_search ||
            product.barcode.includes(this.sale_search)
        );
        if (product_filter.length === 1) {
          this.SearchProduct(product_filter[0]);
        } else {
          this.sale_products_filter = this.products.filter((product) => {
            return (
              product.name
                .toLowerCase()
                .includes(this.sale_search.toLowerCase()) ||
              product.code
                .toLowerCase()
                .includes(this.sale_search.toLowerCase()) ||
              product.barcode
                .toLowerCase()
                .includes(this.sale_search.toLowerCase())
            );
          });
        }
      }, 800);
    },

    //Show Modal (create Client)
    New_Client() {
      this.reset_Form();
      this.$bvModal.show("New_Customer");
    },

    //Reset Form
    reset_Form() {
      this.client = {
        id: "",
        name: "",
        phone: "",
        email: "",
        credit_initial: 0,
      };
    },

    Submit_Customer(e) {
      e.preventDefault();
      this.$refs.Create_Customer.validate().then((success) => {
        if (success) {
          this.Create_Client();
        }
      });
    },

    Create_Client() {
      this.SubmitProcessing = true;
      axios
        .post("clients", {
          name: this.client.name,
          email: "",
          phone: this.client.phone,
          tax_number: 0,
          country: "",
          city: "",
          remise: 0,
          credit_initial: 0,
          adresse: "",
        })
        .then(async (response) => {
          this.SubmitProcessing = false;
          this.$bvModal.hide("New_Customer");
          // Refresh clients list
          const { data } = await axios.get("exchange/create");
          this.clients = data.customers;
          this.$forceUpdate();

          this.makeToast(
            "success",
            this.$t("Create.TitleCustomer"),
            this.$t("Success")
          );
        })
        .catch((error) => {
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          this.SubmitProcessing = false;
        });
    },

    //---Validate State Fields
    getValidationState: function getValidationState(_ref) {
      var dirty = _ref.dirty,
        validated = _ref.validated,
        _ref$valid = _ref.valid,
        valid = _ref$valid === void 0 ? null : _ref$valid;
      return dirty || validated ? valid : null;
    },

    // get Result Value Search Products

    getResultValue(result) {
      return result.code + " " + "(" + result.name + ")";
    },

    // Submit Search Products

    SearchProduct(result) {
      // Check if product already exists in purchase_details
      const existingProduct = this.purchase_details.find(
        item => item.product_id === result.id
      );

      if (existingProduct) {
        this.makeToast(
          "warning",
          this.$t("ProductAlreadyExists"),
          this.$t("Warning")
        );
        this.search_input = "";
        this.$refs.product_autocomplete.value = "";
        this.product_filter = [];
        return;
      }

      this.product = {
        detail_id: 0,
        code: result.code,
        quantity: 1,
        stock: result.qte_purchase,
        fix_stock: result.qte,
        product_variant_id: result.product_variant_id,
        discount: 0,
        DiscountNet: 0,
        discount_Method: "2",
        Net_cost: 0,
        Unit_cost: 0,
        Unit_price: "pc",
        Total_cost: 0,
        tax_method: "1",
        tax_percent: 0,
        taxe: 0,
        product_id: null,
        name: "",
        unitPurchase: "",
        unitSale: "pc",
        purchase_unit_id: "",
        is_imei: 0,
        imei_number: "",
      };

      // Get product details and add to list
      axios
        .get("/show_product_data/" + result.id + "/" + result.product_variant_id)
        .then((response) => {
          Object.assign(this.product, {
            product_id: response.data.id,
            name: response.data.name,
            Net_cost: response.data.Net_cost,
            Unit_cost: response.data.Unit_cost,
            taxe: response.data.tax_cost,
            tax_method: response.data.tax_method,
            tax_percent: response.data.tax_percent,
            unitPurchase: response.data.unitPurchase,
            fix_cost: response.data.fix_cost,
            purchase_unit_id: response.data.purchase_unit_id,
            is_imei: response.data.is_imei,
          });

          this.add_product();
          this.Calcul_Total();
      });

      this.search_input = "";
      this.$refs.product_autocomplete.value = "";
      this.product_filter = [];
    },

    //---------------------------------Get Product Details ------------------------\\

    Get_Product_Details(product_id, variant_id) {
      axios
        .get("/show_product_data/" + product_id + "/" + variant_id)
        .then((response) => {
          this.product.discount = 0;
          this.product.DiscountNet = 0;
          this.product.discount_Method = "2";
          this.product.product_id = response.data.id;
          this.product.name = response.data.name;
          this.product.Net_cost = response.data.Net_cost;
          this.product.Unit_cost = response.data.Unit_cost;
          this.product.Unit_price = response.data.Unit_price;
          this.product.taxe = response.data.tax_cost;
          this.product.tax_method = response.data.tax_method;
          this.product.tax_percent = response.data.tax_percent;
          this.product.unitPurchase = response.data.unitPurchase;
          this.product.fix_cost = response.data.fix_cost;
          this.product.purchase_unit_id = response.data.purchase_unit_id;
          this.product.is_imei = response.data.is_imei;
          this.product.imei_number = "";
          this.add_product();
          this.Calcul_Total();
        });
    },

    searchSale() {
      if (this.timer) {
        clearTimeout(this.timer);
        this.timer = null;
      }

      if (this.sale_search.length < 2) {
        this.sale_products_filter = [];
        return;
      }

      this.timer = setTimeout(() => {
        const product_filter = this.products.filter(
          (product) =>
            product.code === this.sale_search ||
            product.barcode.includes(this.sale_search)
        );
        if (product_filter.length === 1) {
          this.SearchSaleProduct(product_filter[0]);
        } else {
          this.sale_products_filter = this.products.filter((product) => {
            return (
              product.name
                .toLowerCase()
                .includes(this.sale_search.toLowerCase()) ||
              product.code
                .toLowerCase()
                .includes(this.sale_search.toLowerCase()) ||
              product.barcode
                .toLowerCase()
                .includes(this.sale_search.toLowerCase())
            );
          });
        }
      }, 800);
  },

  // Submit Search Products for Sale
  SearchSaleProduct(result) {
    const existingProduct = this.sale_details.find(
      item => item.product_id === result.id
    );

    if (existingProduct) {
      this.makeToast(
        "warning",
        this.$t("ProductAlreadyExists"),
        this.$t("Warning")
      );
      this.sale_search_input = "";
      if (this.$refs.sale_product_autocomplete) {
        this.$refs.sale_product_autocomplete.value = "";
      }
      this.sale_product_filter = [];
      return;
    }

    this.product = {
        detail_id: 0,
        code: result.code,
        quantity: 1,
        stock: result.qte_purchase,
        fix_stock: result.qte,
        product_variant_id: result.product_variant_id,
        discount: 0,
        DiscountNet: 0,
        discount_Method: "2",
        Net_cost: 0,
        Unit_cost: 0,
        Unit_price: "pc",
        Total_cost: 0,
        subtotal: "",
        tax_method: "1",
        tax_percent: 0,
        taxe: 0,
        product_id: null,
        name: "",
        unitSale: "",
        sale_unit_id: "",
        is_imei: 0,
        imei_number: "",
      };

      // Get product details and add to list
      axios
        .get("/show_product_data/" + result.id + "/" + result.product_variant_id)
        .then((response) => {
          Object.assign(this.product, {
            product_id: response.data.id,
            name: response.data.name,
            Net_cost: response.data.Net_cost,
            Unit_cost: response.data.Unit_cost,
            taxe: response.data.tax_cost,
            tax_method: response.data.tax_method,
            tax_percent: response.data.tax_percent,
            unitSale: response.data.unitSale,
            fix_cost: response.data.fix_cost,
            sale_unit_id: response.data.sale_unit_id,
            is_imei: response.data.is_imei,
          });

          this.add_product_sale();
          this.Calcul_Total();
    });        

    this.sale_search_input = "";
    if (this.$refs.sale_product_autocomplete) {
      this.$refs.sale_product_autocomplete.value = "";
    }
    this.sale_product_filter = [];
  },

  handleSaleFocus() {
    this.sale_focused = true;
  },

  handleSaleBlur() {
    setTimeout(() => {
      this.sale_focused = false;
    }, 200);
  },

    //----------------------------------------- Add product -------------------------\\
    add_product() {
      if (this.purchase_details.length > 0) {
        this.Last_Detail_id();
      } else if (this.purchase_details.length === 0) {
        this.product.detail_id = 1;
      }

      this.purchase_details.push(this.product);
      this.calculateTotals();

      this.$nextTick(() => {
        const productsTable = document.getElementById("products-table");
        if (productsTable) {
          const lastRow = productsTable.querySelector("tbody tr:last-child");
          if (lastRow) {
            lastRow.scrollIntoView({ behavior: "smooth", block: "center" });
          }
        }
      });
    },

    add_product_sale() {
      if (this.sale_details.length > 0) {
        this.Last_Detail_id();
      } else if (this.sale_details.length === 0) {
        this.product.detail_id = 1;
      }

      this.sale_details.push(this.product);
      this.calculateTotals();

      this.$nextTick(() => {
        const productsTable = document.getElementById("sales-products-table");
        if (productsTable) {
          const lastRow = productsTable.querySelector("tbody tr:last-child");
          if (lastRow) {
            lastRow.scrollIntoView({ behavior: "smooth", block: "center" });
          }
        }
      });
    },

    //-------------------------------- Get Last Detail Id -------------------------\\
    Last_Detail_id() {
      this.product.detail_id = 0;
      var len = this.purchase_details.length;
      this.product.detail_id = this.purchase_details[len - 1].detail_id + 1;
    },

    //-----------------------------------Verified QTY ------------------------------\\
    Verified_Qty(detail, id) {
      for (var i = 0; i < this.purchase_details.length; i++) {
        if (this.purchase_details[i].detail_id === id) {
          // Ensure quantity is a number and at least 1
          if (isNaN(detail.quantity) || detail.quantity <= 0) {
            this.purchase_details[i].quantity = 1;
          }

          // Recalculate subtotal for this item
          this.calculateLineTotal(this.purchase_details[i]);

          // Update overall totals
          this.Calcul_Total();
          this.$forceUpdate();
        }
      }
    },

    Verified_Sale_Qty(detail, id) {
      for (var i = 0; i < this.sale_details.length; i++) {
        if (this.sale_details[i].detail_id === id) {
          this.Verified_Qty(detail, id);
        }
      }

      if (isNaN(item.quantity) || item.quantity <= 0) {
        item.quantity = 1;
      }
      this.updateSaleSubtotal(item);
    },

    calculateLineTotal(detail) {
      // Calculate discount
      if (detail.discount_Method === "2") {
        // Fixed discount
        detail.DiscountNet = detail.discount;
      } else {
        // Percentage discount
        detail.DiscountNet = parseFloat(
          (detail.Unit_cost * detail.discount) / 100
        );
      }

      // Calculate Net cost and tax based on tax method
      if (detail.tax_method === "1") {
        // Exclusive
        detail.Net_cost = parseFloat(detail.Unit_cost - detail.DiscountNet);
        detail.taxe = parseFloat(
          (detail.tax_percent * (detail.Unit_cost - detail.DiscountNet)) / 100
        );
      } else {
        // Inclusive
        detail.Net_cost = parseFloat(
          (detail.Unit_cost - detail.DiscountNet) /
            (detail.tax_percent / 100 + 1)
        );
        detail.taxe = parseFloat(
          detail.Unit_cost - detail.Net_cost - detail.DiscountNet
        );
      }

      // Calculate subtotal including tax
      detail.subtotal = parseFloat(
        (
          detail.quantity * detail.Net_cost +
          detail.taxe * detail.quantity
        ).toFixed(2)
      );
    },

    //-----------------------------------increment QTY ------------------------------\\

    increment(detail, id) {
      for (var i = 0; i < this.purchase_details.length; i++) {
        if (this.purchase_details[i].detail_id == id) {
          this.formatNumber(this.purchase_details[i].quantity++, 2);
        }
      }
      this.$forceUpdate();
      this.Calcul_Total();
    },

    //-----------------------------------decrement QTY ------------------------------\\

    decrement(detail, id) {
      for (var i = 0; i < this.purchase_details.length; i++) {
        if (this.purchase_details[i].detail_id == id) {
          if (detail.quantity - 1 > 0) {
            this.formatNumber(this.purchase_details[i].quantity--, 2);
          }
        }
      }
      this.$forceUpdate();
      this.Calcul_Total();
    },

    //-----------------------------------------Calcul Total ------------------------------\\
    Calcul_Total() {
      this.purchase_total = 0;
      this.sale_total = 0;

      // Calculate totals for all line items
      this.purchase_details.forEach((detail) => {
        if (!detail) return;
        this.calculateLineTotal(detail);
        this.purchase_total = parseFloat(this.purchase_total) + parseFloat(detail.subtotal);
      });

      this.sale_details.forEach((detail) => {
        if (!detail) return;
        this.calculateLineTotal(detail);
        this.sale_total = parseFloat(this.sale_total) + parseFloat(detail.subtotal);
      });

      this.GrandPurchaseTotal = parseFloat(
        (
          this.total         
        ).toFixed(2)
      );
    },

    //-----------------------------------Delete Detail Product ------------------------------\\
    delete_Product_Detail(id) {
      // Find the index of the product to remove
      const index = this.purchase_details.findIndex((detail) => detail.detail_id === id);

      if (index !== -1) {
        // Remove the product
        this.purchase_details.splice(index, 1);

        // Recalculate totals
        this.Calcul_Total();

        // Force update to refresh the view
        this.$forceUpdate();
      }
    },    

    incrementPurchase(item) {
      item.quantity++;
      this.updatePurchaseSubtotal(item);
    },

    decrementPurchase(item) {
      if (item.quantity > 1) {
        item.quantity--;
        this.updatePurchaseSubtotal(item);
      }
    },

    incrementSale(item) {
      item.quantity++;
      this.updateSaleSubtotal(item);
    },

    decrementSale(item) {
      if (item.quantity > 1) {
        item.quantity--;
        this.updateSaleSubtotal(item);
      }
    },

    updatePurchaseSubtotal(item) {
      item.subtotal = parseFloat((item.quantity * item.Unit_cost).toFixed(2));
      this.calculateTotals();
    },

    updateSaleSubtotal(item) {
      item.subtotal = parseFloat((item.quantity * item.Unit_cost).toFixed(2));
      this.calculateTotals();
    },

    removePurchaseItem(item) {
      const index = this.purchase_details.indexOf(item);
      if (index > -1) {
        this.purchase_details.splice(index, 1);
        this.calculateTotals();
      }
    },

    removeSaleItem(item) {
      const index = this.sale_details.findIndex(
        detail => detail.detail_id === item.detail_id
      );
      if (index !== -1) {
        this.sale_details.splice(index, 1);
        this.calculateTotals();
      }
    },

    calculateTotals() {
      // Calculate purchase total
      this.purchase_total = 0;
      this.sale_total = 0;

      // Calculate purchase total
      this.purchase_total = parseFloat(
        this.purchase_details.reduce((total, item) => {
          return total + (item.quantity * item.Unit_cost);
        }, 0).toFixed(2)
      );

      // Calculate sale total
      this.sale_total = parseFloat(
        this.sale_details.reduce((total, item) => {
          return total + (item.quantity * item.Unit_cost);
        }, 0).toFixed(2)
      );

      this.GrandPurchaseTotal = this.purchase_total;
      this.GrandSaleTotal = this.sale_total;

      // Force update to ensure the view reflects the new totals
      this.$forceUpdate();
    },

    formatNumber(number, dec) {
      const value = (typeof number === "string"
        ? number
        : number.toString()
      ).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec)
        return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}`;
    },

    generateNumber() {
      this.new_product.code = Math.floor(
        Math.pow(10, 7) + Math.random() * (Math.pow(10, 8) - Math.pow(10, 7) - 1)
      );
    },

    async Submit_Exchange() {
      if (this.verifiedForm()) {
        // Start the progress bar
        NProgress.start();
        NProgress.set(0.1);
        this.SubmitProcessing = true;

        console.log('this.sale_details', this.sale_details)

        axios
          .post("purchases", {
            date: this.exchange.date,
            supplier_id: this.exchange.supplier_id,
            warehouse_id: 1,
            statut: "completed",
            notes: "",
            tax_rate: 0,
            TaxNet: 0,
            discount: 0,
            shipping: 0,
            GrandTotal: this.purchase_total,
            details: this.purchase_details
          })
          .then(response => {
            // Complete the animation of theprogress bar.
            NProgress.done();

            this.makeToast(
              "success",
              this.$t("Create.TitlePurchase"),
              this.$t("Success")
            );
            this.SubmitProcessing = false;
          })
          .catch(error => {
            // Complete the animation of theprogress bar.
            NProgress.done();
            this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
            this.SubmitProcessing = false;
          });
        }

        axios
          .post("sales", {
            date: this.exchange.date,
            client_id: this.exchange.customer_id,
            warehouse_id: 1,
            statut: "completed",
            notes: "",
            tax_rate: 0,
            TaxNet: 0,
            discount: 0,
            shipping: 0,
            GrandTotal: this.sale_total,
            details: this.sale_details
          })
          .then(response => {
            NProgress.done();            
          })
          .catch(error => {
            // Complete the animation of theprogress bar.
            NProgress.done();
            this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
            this.SubmitProcessing = false;
        });

        // Prepare the exchange data
        const exchangeData = {
          date: this.exchange.date,
          customer_id: this.exchange.customer_id,
          supplier_id: this.exchange.supplier_id,
          warehouse_id: this.exchange.warehouse_id,
          notes: this.exchange.notes,
          tax_rate: this.exchange.tax_rate || 0,
          TaxNet: this.exchange.TaxNet || 0,
          discount: this.exchange.discount || 0,
          shipping: this.exchange.shipping || 0,
          GrandTotal: this.GrandTotal,
          status: this.exchange.status,
          exchange_in: this.purchase_details.map(item => ({
            product_id: item.product_id,
            product_variant_id: item.product_variant_id,
            quantity: item.quantity,
            price: item.Unit_cost,
            tax_method: item.tax_method,
            tax_percent: item.tax_percent,
            discount: item.discount,
            discount_method: item.discount_Method,
            product_type: item.product_type || 'is_single'
          })),
          exchange_out: this.sale_details.map(item => ({
            product_id: item.product_id,
            product_variant_id: item.product_variant_id,
            quantity: item.quantity,
            price: item.Unit_cost,
            tax_method: item.tax_method,
            tax_percent: item.tax_percent,
            discount: item.discount,
            discount_method: item.discount_Method,
            product_type: item.product_type || 'is_single'
          }))
        };

        // Send request to create exchange
        /*axios.post("exchanges", exchangeData)
          .then(response => {
            this.SubmitProcessing = false;
            NProgress.done();
            this.makeToast(
              "success",
              this.$t("Create.TitleExchange"),
              this.$t("Success")
            );
            this.$router.push({ name: "index_exchanges" });
          })
          .catch(error => {
            this.SubmitProcessing = false;
            NProgress.done();
            this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          });
      }*/
    },

    verifiedForm() {
      if (!this.exchange.date) {
        this.makeToast(
          "warning",
          this.$t("PleaseSelectDate"),
          this.$t("Warning")
        );
        return false;
      }
      console.log(this.exchange);
      if (!this.exchange.customer_id) {
        this.makeToast(
          "warning",
          this.$t("PleaseSelectCustomer"),
          this.$t("Warning")
        );
        return false;
      }      

      if (this.purchase_details.length === 0 || this.sale_details.length === 0) {
        this.makeToast(
          "warning",
          this.$t("PleaseAddProducts"),
          this.$t("Warning")
        );
        return false;
      }

      // Validate quantities
      for (const item of this.purchase_details) {
        if (!item.quantity || item.quantity <= 0) {
          this.makeToast(
            "warning",
            this.$t("PleaseAddValidQuantity"),
            this.$t("Warning")
          );
          return false;
        }
      }

      for (const item of this.sale_details) {
        if (!item.quantity || item.quantity <= 0) {
          this.makeToast(
            "warning",
            this.$t("PleaseAddValidQuantity"), 
            this.$t("Warning")
          );
          return false;
        }
      }

      return true;
    },

    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true,
      });
    },

    //------ Show Modal New Supplier ------\\
    New_Supplier() {
      this.reset_Form_supplier();
      this.$bvModal.show("New_Supplier");
    },

    //------ Reset Form Supplier ------\\
    reset_Form_supplier() {
      this.supplier = {
        id: "",
        name: "",
        phone: "",
        email: "",
        country: "",
        city: "",
        adresse: "",
        tax_number: "",
      };
    },

    handleBlur() {
      setTimeout(() => {
        this.focused = false;
      }, 200);
    },

    // Add this method for generating sale detail IDs
    getLastSaleDetailId() {
      if (this.sale_details.length === 0) {
        return 1;
      }
      return Math.max(...this.sale_details.map(item => item.detail_id)) + 1;
    },

    showNewProductModal() {
      this.$bvModal.show("New_Product");
    },

    hideNewProductModal() {
      this.$bvModal.hide("New_Product");
    },

    async Submit_Product() {
      try {
        // Validate required fields
        if (!this.new_product.name || !this.new_product.cost || !this.new_product.price) {
          this.makeToast(
            "warning",
            this.$t("PleaseEnterAllFields"),
            this.$t("Warning")
          );
          return;
        }

        // Set default values
        this.new_product.unit_id = 1;
        this.new_product.unit_sale_id = 1;
        this.new_product.unit_purchase_id = 1;
        this.new_product.code = this.new_product.code || this.generateProductCode();

        const formData = new FormData();
        Object.entries(this.new_product).forEach(([key, value]) => {
          formData.append(key, value);
        });

        const response = await axios.post("products", formData);
        
        if (response && response.data && response.data.success) {
          this.hideNewProductModal();
          this.makeToast(
            "success",
            this.$t("Create.TitleProduct"),
            this.$t("Success")
          );
          
          // Reset form
          this.new_product = {
            type: "is_single",
            name: "",
            code: "",
            cost: "",
            price: "",
            unit_id: 1,
            unit_sale_id: 1,
            unit_purchase_id: 1,
            stock_alert: "0",
            note: "",
            is_variant: false,
            is_imei: false,
          };
        }
      } catch (error) {
        let errorMessage = ""
        
        if (error.response && error.response.data) {
          if (error.response.data.message) {
            errorMessage = error.response.data.message;
          } else if (error.response.data.errors) {
            // Handle validation errors
            const firstError = Object.values(error.response.data.errors)[0];
            errorMessage = Array.isArray(firstError) ? firstError[0] : firstError;
          }
        }
        
        this.makeToast("danger", errorMessage, this.$t("Failed"));
      }
    },

    // Add helper method to generate product code
    generateProductCode() {
      return Math.floor(Math.pow(10, 7) + Math.random() * (Math.pow(10, 8) - Math.pow(10, 7) - 1));
    },

    // Add these search-related methods
    search(input) {
      if (this.timer) {
        clearTimeout(this.timer);
        this.timer = null;
      }

      if (input.length < 1) {
        this.product_filter = [];
        return;
      }

      this.timer = setTimeout(() => {
        const product_filter = this.products.filter((product) => {
          const name = product.name.toLowerCase();
          const code = product.code.toLowerCase();
          const input_search = input.toLowerCase();
          return (
            name.includes(input_search) ||
            code.includes(input_search)
          );
        });

        if (product_filter.length === 1) {
          this.SearchProduct(product_filter[0]);
        } else {
          this.product_filter = product_filter;
        }
      }, 800);
    },

    getResultValue(result) {
      return `${result.name} (${result.code})`;
    },

    handleFocus() {
      this.focused = true;
    },

    handleBlur() {
      setTimeout(() => {
        this.focused = false;
      }, 200);
    }
  },

  created() {
    this.getInitialData();
    this.Get_Products_By_Warehouse(1);
  },
};
</script>

<style scoped>
.autocomplete {
  position: relative;
}

.autocomplete-result-list {
  position: absolute;
  background: white;
  width: 100%;
  padding: 0;
  margin: 0;
  border: 1px solid #ddd;
  max-height: 200px;
  overflow-y: auto;
  z-index: 1000;
}

.autocomplete-result {
  padding: 8px;
  cursor: pointer;
  list-style: none;
}

.autocomplete-result:hover {
  background: #f5f5f5;
}
</style>
