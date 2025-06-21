<template>
  <div class="main-content">
    <breadcumb :page="$t('Pricing_History')" :folder="$t('Products')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <!-- Filters -->
      <div class="card mb-4">
        <div class="card-body">
          <div class="row">
            <div class="col-md-3">
              <b-form-group :label="$t('Warehouse')">
                <v-select
                  v-model="filters.warehouse_id"
                  :reduce="warehouse => warehouse.id"
                  :placeholder="$t('All_Warehouses')"
                  :options="warehouses"
                  label="name"
                  @input="loadHistory"
                />
              </b-form-group>
            </div>

            <div class="col-md-3">
              <b-form-group :label="$t('Change_Type')">
                <b-form-select v-model="filters.change_type" @change="loadHistory">
                  <option value="">{{ $t('All_Changes') }}</option>
                  <option value="price">{{ $t('Price_Changes') }}</option>
                  <option value="cost">{{ $t('Cost_Changes') }}</option>
                  <option value="both">{{ $t('Both_Price_Cost') }}</option>
                </b-form-select>
              </b-form-group>
            </div>

            <div class="col-md-3">
              <b-form-group :label="$t('Start_Date')">
                <b-form-input
                  v-model="filters.start_date"
                  type="date"
                  @change="loadHistory"
                ></b-form-input>
              </b-form-group>
            </div>

            <div class="col-md-3">
              <b-form-group :label="$t('End_Date')">
                <b-form-input
                  v-model="filters.end_date"
                  type="date"
                  @change="loadHistory"
                ></b-form-input>
              </b-form-group>
            </div>
          </div>

          <div class="row" v-if="selectedProduct">
            <div class="col-md-12">
              <div class="alert alert-info">
                <strong>{{ $t('Product') }}:</strong> {{ selectedProduct.name }}
                <span v-if="selectedProduct.variant_name">
                  - {{ selectedProduct.variant_name }}
                </span>
                <br>
                <strong>{{ $t('Warehouse') }}:</strong> {{ selectedProduct.warehouse_name }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- History Table -->
      <div v-if="!filters.warehouse_id && !isLoading" class="alert alert-info">
        <i class="i-Information mr-2"></i>
        {{ $t('Please_select_a_warehouse_to_view_pricing_history') }}
      </div>

      <vue-good-table
        v-else
        mode="remote"
        :columns="columns"
        :totalRows="totalRows"
        :rows="history"
        @on-page-change="onPageChange"
        @on-per-page-change="onPerPageChange"
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :search-options="{
          enabled: true,
          placeholder: $t('Search_this_table'),
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
          <span v-if="props.column.field == 'change_type'">
            <b-badge
              :variant="getChangeTypeVariant(props.row.change_type)"
              class="mr-1"
            >
              {{ $t(formatChangeType(props.row.change_type)) }}
            </b-badge>
          </span>

          <div v-else-if="props.column.field == 'price_changes'">
            <div v-if="props.row.old_price || props.row.new_price">
              <span class="text-muted">{{ props.row.old_price || 'N/A' }}</span>
              <i class="i-Arrow-Right mx-2"></i>
              <span class="text-primary font-weight-bold">{{ props.row.new_price || 'N/A' }}</span>
              <br>
              <small v-if="props.row.old_price && props.row.new_price" class="text-muted">
                {{ $t('Change') }}: {{ calculateChange(props.row.old_price, props.row.new_price) }}
              </small>
            </div>
            <span v-else class="text-muted">{{ $t('No_Change') }}</span>
          </div>

          <div v-else-if="props.column.field == 'cost_changes'">
            <div v-if="props.row.old_cost || props.row.new_cost">
              <span class="text-muted">{{ props.row.old_cost || 'N/A' }}</span>
              <i class="i-Arrow-Right mx-2"></i>
              <span class="text-success font-weight-bold">{{ props.row.new_cost || 'N/A' }}</span>
              <br>
              <small v-if="props.row.old_cost && props.row.new_cost" class="text-muted">
                {{ $t('Change') }}: {{ calculateChange(props.row.old_cost, props.row.new_cost) }}
              </small>
            </div>
            <span v-else class="text-muted">{{ $t('No_Change') }}</span>
          </div>

          <div v-else-if="props.column.field == 'product_info'">
            <div>
              <strong>{{ props.row.product_name }}</strong>
              <small v-if="props.row.product_code" class="d-block text-muted">
                {{ $t('Code') }}: {{ props.row.product_code }}
              </small>
              <small v-if="props.row.variant_name" class="d-block text-info">
                {{ props.row.variant_name }}
              </small>
            </div>
          </div>

          <span v-else-if="props.column.field == 'reason'">
            <span v-if="props.row.reason" class="text-wrap">{{ $t(props.row.reason) }}</span>
            <span v-else class="text-muted font-italic">{{ $t('No_reason_provided') }}</span>
          </span>

          <span v-else-if="props.column.field == 'changed_at'">
            {{ formatDate(props.row.changed_at) }}
          </span>

          <span v-else>{{ props.formattedRow[props.column.field] }}</span>
        </template>
      </vue-good-table>
    </div>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";

export default {
  metaInfo: {
    title: "Pricing History"
  },
  data() {
    return {
      isLoading: true,
      serverParams: {
        columnFilters: {},
        sort: {
          field: "created_at",
          type: "desc"
        },
        page: 1,
        perPage: 20
      },
      totalRows: 0,
      search: "",
      history: [],
      warehouses: [],
      selectedProduct: null,
      filters: {
        warehouse_id: "",
        change_type: "",
        start_date: "",
        end_date: "",
        product_id: "",
        product_variant_id: ""
      },
      columns: [
        {
          label: this.$t("Date"),
          field: "changed_at",
          tdClass: "text-left",
          thClass: "text-left",
          width: "150px"
        },
        {
          label: this.$t("Product"),
          field: "product_info",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Change_Type"),
          field: "change_type",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Price_Changes"),
          field: "price_changes",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Cost_Changes"),
          field: "cost_changes",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Reason"),
          field: "reason",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Changed_By"),
          field: "changed_by",
          tdClass: "text-left",
          thClass: "text-left"
        }
      ]
    };
  },

  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"])
  },

  methods: {
    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange(params) {
      this.updateParams({ page: params.currentPage });
      this.loadHistory();
    },

    //---- Event Per Page Change
    onPerPageChange(params) {
      this.updateParams({ perPage: params.currentPerPage });
      this.loadHistory();
    },

    //---- Event Sort Change
    onSortChange(params) {
      this.updateParams({
        sort: {
          type: params[0].type,
          field: params[0].field
        }
      });
      this.loadHistory();
    },

    //---- Event Search
    onSearch(params) {
      this.search = params.searchTerm;
      this.loadHistory();
    },

    //---- Load History
    loadHistory() {
      // Don't load history if no warehouse is selected
      if (!this.filters.warehouse_id) {
        console.log('No warehouse selected, skipping history load');
        this.isLoading = false;
        this.history = [];
        this.totalRows = 0;
        return;
      }

      this.isLoading = true;

      let url = `/warehouse-pricing/warehouse-history?page=${this.serverParams.page}&limit=${this.serverParams.perPage}&search=${this.search}&SortField=${this.serverParams.sort.field}&SortType=${this.serverParams.sort.type}`;
      
      // Add filters
      Object.keys(this.filters).forEach(key => {
        if (this.filters[key] !== "" && this.filters[key] !== null) {
          url += `&${key}=${this.filters[key]}`;
        }
      });

      console.log('Loading history from:', url);
      console.log('Current filters:', this.filters);

      axios
        .get(url)
        .then(response => {
          console.log('History Response:', response.data);
          
          if (response.data) {
            this.history = response.data.history || [];
            this.totalRows = response.data.totalRows || 0;
            
            // Get warehouses from the response if available and not already loaded
            if (response.data.warehouses && this.warehouses.length === 0) {
              this.warehouses = response.data.warehouses;
              console.log('Warehouses loaded from history response:', this.warehouses.length);
            }
            
            if (response.data.warehouse) {
              this.selectedWarehouse = response.data.warehouse;
            }
            
            console.log('History loaded:', this.history.length);
            console.log('Total rows:', this.totalRows);
          } else {
            console.warn('No data in history response');
            this.history = [];
            this.totalRows = 0;
          }
          
          this.isLoading = false;
        })
        .catch(error => {
          console.error('History API Error:', error);
          console.error('Error response:', error.response);
          console.error('Error status:', error.response?.status);
          console.error('Error data:', error.response?.data);
          
          this.isLoading = false;
          this.history = [];
          this.totalRows = 0;
          
          // Show more specific error messages
          if (error.response?.status === 401) {
            this.makeToast("danger", "Authentication required. Please login again.", "Authentication Error");
          } else if (error.response?.status === 403) {
            this.makeToast("danger", "You don't have permission to access pricing history.", "Permission Error");
          } else if (error.response?.status === 404) {
            this.makeToast("danger", "Pricing history endpoint not found.", "Not Found");
          } else if (error.response?.status === 422) {
            this.makeToast("warning", "Please select a warehouse to view pricing history.", "Validation Error");
          } else {
            this.makeToast("danger", `API Error: ${error.message}`, "Failed");
          }
        });
    },

    //---- Load Warehouses
    loadWarehouses() {
      // Get warehouses from warehouse-pricing endpoint without warehouse filter
      axios
        .get("/warehouse-pricing?page=1&limit=1")
        .then(response => {
          console.log('Warehouses Response:', response.data);
          if (response.data && response.data.warehouses) {
            this.warehouses = response.data.warehouses;
            console.log('Warehouses loaded:', this.warehouses.length);
          } else {
            console.warn('No warehouses found in response');
            this.warehouses = [];
          }
        })
        .catch(error => {
          console.error("Error loading warehouses:", error);
          this.makeToast("danger", "Failed to load warehouses", "Error");
        });
    },

    //---- Get Change Type Variant
    getChangeTypeVariant(changeType) {
      switch (changeType) {
        case "price":
          return "primary";
        case "cost":
          return "success";
        case "both":
        case "price,cost":
        case "cost,price":
          return "info";
        default:
          return "secondary";
      }
    },

    //---- Format Change Type
    formatChangeType(changeType) {
      switch (changeType) {
        case "price":
          return this.$t("Price");
        case "cost":
          return this.$t("Cost");
        case "both":
        case "price,cost":
        case "cost,price":
          return this.$t("Both");
        default:
          return changeType;
      }
    },

    //---- Calculate Change
    calculateChange(oldValue, newValue) {
      if (!oldValue || !newValue) return "";
      
      const old = parseFloat(oldValue.replace(/,/g, ''));
      const newVal = parseFloat(newValue.replace(/,/g, ''));
      const change = newVal - old;
      const percentage = ((change / old) * 100).toFixed(2);
      
      const sign = change >= 0 ? "+" : "";
      return `${sign}${change.toFixed(2)} (${sign}${percentage}%)`;
    },

    //---- Format Date
    formatDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString() + " " + date.toLocaleTimeString();
    },

    //---- Toast
    makeToast(variant, msg, title) {
      this.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    }
  },

  //---- Created function
  created() {
    // Get filters from query parameters
    if (this.$route.query.product_id) {
      this.filters.product_id = this.$route.query.product_id;
    }
    if (this.$route.query.warehouse_id) {
      this.filters.warehouse_id = this.$route.query.warehouse_id;
    }
    if (this.$route.query.product_variant_id) {
      this.filters.product_variant_id = this.$route.query.product_variant_id;
    }

    // If we have specific product/warehouse, load product info
    if (this.filters.product_id && this.filters.warehouse_id) {
      this.selectedProduct = {
        name: this.$route.query.product_name || "Product",
        variant_name: this.$route.query.variant_name || null,
        warehouse_name: this.$route.query.warehouse_name || "Warehouse"
      };
    }

    // Load warehouses first
    this.loadWarehouses();
    
    // Only load history if warehouse is pre-selected from query parameters
    if (this.filters.warehouse_id) {
      this.loadHistory();
    } else {
      // Set loading to false and show message to select warehouse
      this.isLoading = false;
    }
  }
};
</script>

<style scoped>
.alert {
  margin-bottom: 1rem;
}

.text-wrap {
  word-wrap: break-word;
  max-width: 200px;
}

.font-italic {
  font-style: italic;
}
</style> 