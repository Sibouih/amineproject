<template>
  <div class="main-content">
    <breadcumb :page="$t('Warehouse_Pricing')" :folder="$t('Products')"/>

    <!-- Warehouse Filter Section -->
    <div class="row mb-3">
      <div class="col-md-3">
        <div class="form-group">
          <label>{{ $t('Select_Warehouse') }}</label>
          <v-select
            v-model="Filter_warehouse"
            :reduce="warehouse => warehouse.id"
            :placeholder="$t('Choose_Warehouse')"
            :options="warehouses"
            label="name"
            @input="resetPageAndReload"
          />
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
          <label>{{ $t('Filter_by_Product') }}</label>
          <v-select
            v-model="Filter_product"
            :reduce="product => product.id"
            :placeholder="$t('All_Products')"
            :options="all_products"
            label="display_name"
            @input="resetPageAndReload"
            :clearable="true"
          />
        </div>
      </div>
      <div class="col-md-3" v-if="selectedIds.length > 0">
        <div class="form-group">
          <label>&nbsp;</label>
          <button
            @click="showBulkUpdateModal = true"
            class="btn btn-primary btn-sm d-block"
          >
            {{ $t('Bulk_Update_Pricing') }} ({{ selectedIds.length }})
          </button>
        </div>
      </div>
      <div class="col-md-3">
        <div class="form-group">
          <label>&nbsp;</label>
          <button
            @click="clearFilters"
            class="btn btn-secondary btn-sm d-block"
          >
            {{ $t('Clear_Filters') }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else-if="products.length === 0 && !isLoading">
      <div class="alert alert-warning">
        <h5><i class="i-Warning"></i> {{ $t('No_Products_Found') }}</h5>
        <p>{{ $t('No_products_found_with_current_filters') }}</p>
      </div>
    </div>
    <div v-else>
      <vue-good-table
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="products"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        @on-selected-rows-change="selectionChanged"
        :search-options="{
          enabled: true,
          placeholder: $t('Search_this_table'),
        }"
        :select-options="{
          enabled: true,
          selectOnCheckboxOnly: true,
          selectionInfoClass: 'custom-class',
          selectionText: 'rows selected',
          clearSelectionText: 'clear',
          disableSelectInfo: true,
          selectAllByGroup: true,
        }"
        :pagination-options="{
          enabled: true,
          mode: 'records',
          nextLabel: 'next',
          prevLabel: 'prev',
        }"
        styleClass="tableOne table-hover vgt-table"
      >
        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">
            <a @click="editPricing(props.row)" class="cursor-pointer">
              <i class="i-Edit text-25 text-success"></i>
            </a>
            <a @click="viewHistory(props.row)" class="cursor-pointer ml-2">
              <i class="i-Clock text-25 text-info"></i>
            </a>
          </span>

          <div v-else-if="props.column.field == 'product_name'">
            <span>{{ props.row.product_name }}</span>
            <small v-if="props.row.variant_name" class="d-block text-muted">
              {{ props.row.variant_name }}
            </small>
          </div>

          <div v-else-if="props.column.field == 'pricing_comparison'">
            <div class="row">
              <div class="col-6">
                <small class="text-muted">{{ $t('Warehouse') }}:</small><br>
                <strong class="text-primary">{{ $t('Price') }} {{ props.row.price }}</strong><br>
                <span class="text-secondary">{{ $t('Cost') }} {{ props.row.cost }}</span>
              </div>
              <div class="col-6">
                <small class="text-muted">{{ $t('Global') }}:</small><br>
                <span>{{ $t('Price') }} {{ props.row.global_price }}</span><br>
                <span class="text-secondary">{{ $t('Cost') }} {{ props.row.global_cost }}</span>
              </div>
            </div>
          </div>

          <span v-else-if="props.column.field == 'profit_margin'">
            <span :class="parseFloat(props.row.profit_margin) < 10 ? 'text-danger' : 'text-success'">
              {{ props.row.profit_margin }}%
            </span>
          </span>

          <span v-else>{{ props.formattedRow[props.column.field] }}</span>
        </template>
      </vue-good-table>
    </div>

    <!-- Edit Pricing Modal -->
    <validation-observer ref="edit_pricing">
      <b-modal hide-footer size="md" id="editPricingModal" :title="$t('Edit_Pricing')">
        <b-form @submit.prevent="updatePricing">
          <b-row>
            <b-col md="12">
              <h6>{{ editingProduct.product_name }}</h6>
              <small v-if="editingProduct.variant_name" class="text-muted">
                {{ editingProduct.variant_name }}
              </small>
              <small class="d-block text-muted">{{ editingProduct.warehouse_name }}</small>
            </b-col>

            <b-col md="6">
              <validation-provider name="price" rules="numeric|min_value:0" v-slot="{ errors }">
                <b-form-group :label="$t('Price')">
                  <b-form-input
                    v-model="editingProduct.price"
                    type="number"
                    step="0.01"
                    min="0"
                  ></b-form-input>
                </b-form-group>
                <span class="error">{{ errors && errors[0] ? errors[0] : '' }}</span>
              </validation-provider>
            </b-col>

            <b-col md="6">
              <validation-provider name="cost" rules="numeric|min_value:0" v-slot="{ errors }">
                <b-form-group :label="$t('Cost')">
                  <b-form-input
                    v-model="editingProduct.cost"
                    type="number"
                    step="0.01"
                    min="0"
                  ></b-form-input>
                </b-form-group>
                <span class="error">{{ errors && errors[0] ? errors[0] : '' }}</span>
              </validation-provider>
            </b-col>

            <b-col md="12">
              <b-form-group :label="$t('Reason')">
                <b-form-textarea
                  v-model="editingProduct.reason"
                  rows="3"
                  :placeholder="$t('Optional_reason_for_change')"
                ></b-form-textarea>
              </b-form-group>
            </b-col>

            <b-col md="12" class="mt-3">
              <b-button @click="updatePricing" type="submit" variant="primary" class="mr-2">
                {{ $t('Update') }}
              </b-button>
              <b-button @click="$bvModal.hide('editPricingModal')" variant="secondary">
                {{ $t('Cancel') }}
              </b-button>
            </b-col>
          </b-row>
        </b-form>
      </b-modal>
    </validation-observer>

    <!-- Bulk Update Modal -->
    <b-modal v-model="showBulkUpdateModal" hide-footer size="md" :title="$t('Bulk_Update_Pricing')">
      <b-form @submit.prevent="bulkUpdatePricing">
        <b-row>
          <b-col md="12">
            <p>{{ $t('Update_pricing_for') }} {{ selectedIds.length }} {{ $t('products') }}</p>
          </b-col>

          <b-col md="6">
            <b-form-group :label="$t('Price_Adjustment')">
              <b-form-select v-model="bulkUpdate.priceType">
                <option value="">{{ $t('No_Change') }}</option>
                <option value="fixed">{{ $t('Set_Fixed_Price') }}</option>
                <option value="percentage">{{ $t('Percentage_Change') }}</option>
              </b-form-select>
            </b-form-group>
          </b-col>

          <b-col md="6" v-if="bulkUpdate.priceType">
            <b-form-group :label="bulkUpdate.priceType === 'fixed' ? $t('New_Price') : $t('Percentage')">
              <b-form-input
                v-model="bulkUpdate.priceValue"
                type="number"
                step="0.01"
                :min="bulkUpdate.priceType === 'percentage' ? -100 : 0"
              ></b-form-input>
            </b-form-group>
          </b-col>

          <b-col md="6">
            <b-form-group :label="$t('Cost_Adjustment')">
              <b-form-select v-model="bulkUpdate.costType">
                <option value="">{{ $t('No_Change') }}</option>
                <option value="fixed">{{ $t('Set_Fixed_Cost') }}</option>
                <option value="percentage">{{ $t('Percentage_Change') }}</option>
              </b-form-select>
            </b-form-group>
          </b-col>

          <b-col md="6" v-if="bulkUpdate.costType">
            <b-form-group :label="bulkUpdate.costType === 'fixed' ? $t('New_Cost') : $t('Percentage')">
              <b-form-input
                v-model="bulkUpdate.costValue"
                type="number"
                step="0.01"
                :min="bulkUpdate.costType === 'percentage' ? -100 : 0"
              ></b-form-input>
            </b-form-group>
          </b-col>

          <b-col md="12">
            <b-form-group :label="$t('Reason')">
              <b-form-textarea
                v-model="bulkUpdate.reason"
                rows="3"
                :placeholder="$t('Reason_for_bulk_update')"
              ></b-form-textarea>
            </b-form-group>
          </b-col>

          <b-col md="12" class="mt-3">
            <b-button @click="bulkUpdatePricing" type="submit" variant="primary" class="mr-2">
              {{ $t('Update_All') }}
            </b-button>
            <b-button @click="showBulkUpdateModal = false" variant="secondary">
              {{ $t('Cancel') }}
            </b-button>
          </b-col>
        </b-row>
      </b-form>
    </b-modal>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";

export default {
  metaInfo: {
    title: "Warehouse Pricing"
  },
  data() {
    return {
      isLoading: true,
      serverParams: {
        columnFilters: {},
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      selectedIds: [],
      totalRows: 0,
      search: "",
      products: [],
      warehouses: [],
      all_products: [],
      Filter_warehouse: "",
      Filter_product: "",
      editingProduct: {},
      showBulkUpdateModal: false,
      bulkUpdate: {
        priceType: "",
        priceValue: "",
        costType: "",
        costValue: "",
        reason: ""
      },
      columns: [
        {
          label: this.$t("Product"),
          field: "product_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Code"),
          field: "product_code",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Quantity"),
          field: "quantity",
          type: "number",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Pricing_Comparison"),
          field: "pricing_comparison",
          sortable: false,
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Profit_Margin"),
          field: "profit_margin",
          type: "number",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Action"),
          field: "actions",
          sortable: false,
          tdClass: "text-right",
          thClass: "text-right"
        }
      ]
    };
  },

  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"])
  },

  methods: {
    ...mapActions(["refreshUserPermissions"]),
    
    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange(params) {
      this.updateParams({ page: params.currentPage });
      this.Get_Products_By_Warehouse(this.serverParams.page);
    },

    //---- Event Per Page Change
    onPerPageChange(params) {
      this.updateParams({ perPage: params.currentPerPage });
      this.Get_Products_By_Warehouse(this.serverParams.page);
    },

    //---- Event Sort Change
    onSortChange(params) {
      this.updateParams({
        sort: {
          type: params[0].type,
          field: params[0].field
        }
      });
      this.Get_Products_By_Warehouse(this.serverParams.page);
    },

    //---- Event Search
    onSearch(params) {
      this.search = params.searchTerm;
      this.serverParams.page = 1; // Reset to page 1 when searching
      this.Get_Products_By_Warehouse(1);
    },

    //---- Event Select Rows
    selectionChanged(params) {
      this.selectedIds = [];
      if (params && params.selectedRows) {
        params.selectedRows.forEach(row => {
          this.selectedIds.push(row.id);
        });
      }
      console.log('Selected IDs:', this.selectedIds);
    },

    //---- Get Products by Warehouse
    Get_Products_By_Warehouse(page = 1) {
      // Start Loading
      this.isLoading = true;

      // Reset to page 1 when filters change (unless explicitly requesting a specific page)
      if (page === 1) {
        this.serverParams.page = 1;
      }

      let url = `/warehouse-pricing?page=${page}&limit=${this.serverParams.perPage}&search=${this.search}&SortField=${this.serverParams.sort.field}&SortType=${this.serverParams.sort.type}`;
      
      if (this.Filter_warehouse !== "" && this.Filter_warehouse !== null) {
        url += `&warehouse_id=${this.Filter_warehouse}`;
      }

      if (this.Filter_product !== "" && this.Filter_product !== null) {
        url += `&product_id=${this.Filter_product}`;
      }

      console.log('Fetching data from:', url);
      console.log('Current filters:', {
        warehouse: this.Filter_warehouse,
        product: this.Filter_product,
        search: this.search
      });

      axios
        .get(url)
        .then(response => {
          console.log('API Response:', response.data);
          
          if (response.data) {
            this.products = response.data.products || [];
            this.totalRows = response.data.totalRows || 0;
            
            // Get warehouses from the response if available
            if (response.data.warehouses) {
              this.warehouses = response.data.warehouses;
              console.log('Warehouses loaded from response:', this.warehouses.length);
            }
            
            // Get all products from the response if available
            if (response.data.all_products) {
              this.all_products = response.data.all_products;
              console.log('All products loaded from response:', this.all_products.length);
            }

            // Log debug information if available
            if (response.data.debug) {
              console.log('Debug info:', response.data.debug);
            }
            
            console.log('Products loaded:', this.products.length);
            console.log('Total rows:', this.totalRows);

            // If we're on a page beyond available data, go to page 1
            if (response.data.currentPage && response.data.currentPage !== page) {
              console.log(`Redirected from page ${page} to page ${response.data.currentPage}`);
              this.serverParams.page = response.data.currentPage;
            }
          } else {
            console.warn('No data in response');
            this.products = [];
            this.totalRows = 0;
          }
          
          this.isLoading = false;
        })
        .catch(error => {
          console.error('API Error:', error);
          console.error('Error response:', error.response);
          console.error('Error status:', error.response?.status);
          console.error('Error data:', error.response?.data);
          console.error('Request config:', error.config);
          
          this.isLoading = false;
          this.products = [];
          this.totalRows = 0;
          
          // Show more specific error messages
          if (error.response?.status === 401) {
            this.makeToast("danger", "Authentication required. Please login again.", "Authentication Error");
            // Try to refresh user permissions
            this.refreshUserPermissions();
          } else if (error.response?.status === 403) {
            this.makeToast("danger", "You don't have permission to access warehouse pricing.", "Permission Error");
          } else if (error.response?.status === 404) {
            this.makeToast("danger", "Warehouse pricing endpoint not found.", "Not Found");
          } else {
            this.makeToast("danger", `API Error: ${error.message}`, "Failed");
          }
        });
    },

    //---- Get Warehouses
    Get_Warehouses() {
      this.isLoading = true;
      
      // Get warehouses from warehouse-pricing endpoint without warehouse filter
      axios
        .get('/warehouse-pricing?page=1&limit=1')
        .then(response => {
          console.log('Warehouses Response:', response.data);
          if (response.data && response.data.warehouses) {
            this.warehouses = response.data.warehouses;
            console.log('Warehouses loaded:', this.warehouses.length);
          } else {
            console.warn('No warehouses found in response');
            this.warehouses = [];
          }
          this.isLoading = false;
        })
        .catch(error => {
          console.error('Error loading warehouses:', error);
          this.isLoading = false;
          this.makeToast("danger", "Failed to load warehouses", "Error");
        });
    },

    //---- Edit Pricing
    editPricing(product) {
      this.editingProduct = { ...product };
      this.editingProduct.reason = "";
      this.$bvModal.show("editPricingModal");
    },

    //---- Update Pricing
    updatePricing() {
      this.$refs.edit_pricing.validate().then(success => {
        if (!success) {
          return;
        }

        const data = {
          product_warehouse_id: this.editingProduct.id,
          price: this.editingProduct.price,
          cost: this.editingProduct.cost,
          reason: this.editingProduct.reason
        };

        axios
          .post("/warehouse-pricing/update", data)
          .then(response => {
            this.$bvModal.hide("editPricingModal");
            this.makeToast("success", this.$t("Update"), this.$t("Success"));
            this.Get_Products_By_Warehouse(this.serverParams.page);
          })
          .catch(error => {
            this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
          });
      });
    },

    //---- Bulk Update Pricing
    bulkUpdatePricing() {
      if (!this.bulkUpdate.priceType && !this.bulkUpdate.costType) {
        this.makeToast("warning", this.$t("Please_select_update_type"), this.$t("Warning"));
        return;
      }

      const updates = [];
      this.selectedIds.forEach(id => {
        const product = this.products.find(p => p.id === id);
        if (product) {
          const update = {
            product_id: product.product_id,
            product_variant_id: product.product_variant_id
          };

          if (this.bulkUpdate.priceType === "fixed") {
            update.price = this.bulkUpdate.priceValue;
          } else if (this.bulkUpdate.priceType === "percentage") {
            const currentPrice = parseFloat(product.price.replace(/,/g, ''));
            update.price = currentPrice * (1 + this.bulkUpdate.priceValue / 100);
          }

          if (this.bulkUpdate.costType === "fixed") {
            update.cost = this.bulkUpdate.costValue;
          } else if (this.bulkUpdate.costType === "percentage") {
            const currentCost = parseFloat(product.cost.replace(/,/g, ''));
            update.cost = currentCost * (1 + this.bulkUpdate.costValue / 100);
          }

          updates.push(update);
        }
      });

      const data = {
        warehouse_id: this.Filter_warehouse,
        updates: updates,
        reason: this.bulkUpdate.reason
      };

      axios
        .post("/warehouse-pricing/bulk-update", data)
        .then(response => {
          this.showBulkUpdateModal = false;
          this.selectedIds = [];
          this.bulkUpdate = {
            priceType: "",
            priceValue: "",
            costType: "",
            costValue: "",
            reason: ""
          };
          this.makeToast("success", this.$t("Update"), this.$t("Success"));
          this.Get_Products_By_Warehouse(this.serverParams.page);
        })
        .catch(error => {
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },

    //---- View History
    viewHistory(product) {
      this.$router.push({
        name: "warehouse_pricing_history",
        query: {
          product_id: product.product_id,
          warehouse_id: product.warehouse_id,
          product_variant_id: product.product_variant_id
        }
      });
    },

    //---- Toast
    makeToast(variant, msg, title) {
      this.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    //---- Clear Filters
    clearFilters() {
      this.Filter_warehouse = "";
      this.Filter_product = "";
      this.serverParams.page = 1; // Reset to page 1
      this.Get_Products_By_Warehouse(1);
    },

    //---- Reset page when filters change
    resetPageAndReload() {
      this.serverParams.page = 1;
      this.Get_Products_By_Warehouse(1);
    }
  },

  //---- Mounted function
  mounted() {
    // Event listeners are now handled directly in the vue-good-table component
  },

  //---- Created function
  created() {
    // Load warehouses and products on component creation
    this.Get_Warehouses();
    // Load initial data (this will load all products for dropdown even without warehouse filter)
    this.Get_Products_By_Warehouse();
  }
};
</script> 