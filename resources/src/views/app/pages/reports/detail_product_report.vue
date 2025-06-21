<template>
  <div class="main-content">
    <breadcumb :page="$t('product_report')" :folder="$t('Reports')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-row v-if="!isLoading">

      <b-col md="12" class="text-center">
        <date-range-picker 
          v-model="dateRange" 
          :startDate="startDate" 
          :endDate="endDate" 
           @update="Submit_filter_dateRange"
          :locale-data="locale" > 

          <template v-slot:input="picker" style="min-width: 350px;">
              {{ picker.startDate.toJSON().slice(0, 10)}} - {{ picker.endDate.toJSON().slice(0, 10)}}
          </template>        
        </date-range-picker>
      </b-col>

       <!-- product variant -->
       <b-col md="5" class="mt-4" v-if="product.type == 'is_variant'">
            <table class="table table-hover table-sm">
              <thead>
                <tr>
                  <th>{{$t('Variant_code')}}</th>
                  <th>{{$t('Variant_Name')}}</th>
                  <th>{{$t('Variant_price')}}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="product_variant_data in product.products_variants_data">
                  <td>{{product_variant_data.code}}</td>
                  <td>{{product_variant_data.name}}</td>
                  <td>{{currentUser.currency}} {{product_variant_data.price}}</td>
                </tr>
              </tbody>
            </table>
          </b-col>

      <!-- Summary Cards -->
      <b-col md="12" class="mt-4" v-if="!isLoading">
        <b-row>
          <!-- Sales Card -->
          <b-col lg="3" md="6" sm="12">
            <b-card class="card-icon-bg card-icon-bg-primary o-hidden mb-30 text-center">
              <i class="i-Checkout"></i>
              <div class="content">
                <p class="text-muted mt-2 mb-0">{{$t('Sales')}}</p>
                <p class="text-primary text-24 line-height-1 mb-2">{{totalSalesCount}}</p>
                <p class="text-muted mb-0">{{currentUser.currency}} {{formatNumber(totalSalesAmount, 2)}}</p>
              </div>
            </b-card>
          </b-col>

          <!-- Purchases Card -->
          <b-col lg="3" md="6" sm="12">
            <b-card class="card-icon-bg card-icon-bg-success o-hidden mb-30 text-center">
              <i class="i-Shopping-Cart"></i>
              <div class="content">
                <p class="text-muted mt-2 mb-0">{{$t('Purchases')}}</p>
                <p class="text-success text-24 line-height-1 mb-2">{{totalPurchasesCount}}</p>
                <p class="text-muted mb-0">{{currentUser.currency}} {{formatNumber(totalPurchasesAmount, 2)}}</p>
              </div>
            </b-card>
          </b-col>

          <!-- Sales Returns Card -->
          <b-col lg="3" md="6" sm="12">
            <b-card class="card-icon-bg card-icon-bg-warning o-hidden mb-30 text-center">
              <i class="i-Arrow-Back"></i>
              <div class="content">
                <p class="text-muted mt-2 mb-0">{{$t('Sales_Returns')}}</p>
                <p class="text-warning text-24 line-height-1 mb-2">{{totalSalesReturnsCount}}</p>
                <p class="text-muted mb-0">{{currentUser.currency}} {{formatNumber(totalSalesReturnsAmount, 2)}}</p>
              </div>
            </b-card>
          </b-col>

          <!-- Purchase Returns Card -->
          <b-col lg="3" md="6" sm="12">
            <b-card class="card-icon-bg card-icon-bg-danger o-hidden mb-30 text-center">
              <i class="i-Arrow-Forward"></i>
              <div class="content">
                <p class="text-muted mt-2 mb-0">{{$t('Purchase_Returns')}}</p>
                <p class="text-danger text-24 line-height-1 mb-2">{{totalPurchaseReturnsCount}}</p>
                <p class="text-muted mb-0">{{currentUser.currency}} {{formatNumber(totalPurchaseReturnsAmount, 2)}}</p>
              </div>
            </b-card>
          </b-col>
        </b-row>
      </b-col>

      <b-col md="12">
        <b-card class="card mb-30" header-bg-variant="transparent ">
          <b-tabs active-nav-item-class="nav nav-tabs" content-class="mt-3">
           

            <!-- Sales Table -->
            <b-row v-if="!isLoading">
              <b-col md="12">
                <b-tab :title="$t('Sales')">
                    <vue-good-table
                      mode="remote"
                      :columns="columns_sales"
                      :totalRows="totalRows_sales"
                      :rows="rows_sales"
                      :group-options="{
                        enabled: true,
                        headerPosition: 'bottom',
                      }"
                      @on-page-change="PageChangeSales"
                      @on-per-page-change="onPerPageChangeSales"
                      @on-search="onSearch_sales"
                      :search-options="{
                        placeholder: $t('Search_this_table'),
                        enabled: true,
                      }"
                      :pagination-options="{
                        enabled: true,
                        mode: 'records',
                        nextLabel: 'next',
                        prevLabel: 'prev',
                        perPageDropdown: [10, 25, 50, 100, 250, 500],
                      }"
                      styleClass="tableOne table-hover vgt-table"
                    >
                    <div slot="table-actions" class="mt-2 mb-3">
                      <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right>
                        <i class="i-Filter-2"></i>
                        {{ $t("Filter") }}
                      </b-button>

                      <b-button @click="Sales_PDF()" size="sm" variant="outline-success ripple m-1">
                        <i class="i-File-Copy"></i> PDF
                      </b-button>

                      <vue-excel-xlsx
                          class="btn btn-sm btn-outline-danger ripple m-1"
                          :data="sales"
                          :columns="columns_sales"
                          :file-name="'product_report'"
                          :file-type="'xlsx'"
                          :sheet-name="'product_report'"
                          >
                          <i class="i-File-Excel"></i> EXCEL
                      </vue-excel-xlsx>

                    </div>
                      <template slot="table-row" slot-scope="props">
                        <div v-if="props.column.field == 'Ref'">
                          <router-link
                            :to="'/app/sales/detail/'+props.row.sale_id"
                          >
                            <span class="ul-btn__text ml-1">{{props.row.Ref}}</span>
                          </router-link>
                        </div>

                        <div v-else-if="props.column.field == 'total'">
                          <span>{{currentUser.currency}} {{props.row.total}}</span>
                        </div>

                      </template>
                    </vue-good-table>
                </b-tab>
              </b-col>
            </b-row>

            <!-- Purchase Table -->
            <b-row v-if="!isLoading">
              <b-col md="12">                
                <b-tab :title="$t('Purchases')">
                  <vue-good-table
                    mode="remote"
                    :columns="columns_purchases"
                    :totalRows="totalRows_purchases"
                    :rows="rows_purchases"
                    :group-options="{
                      enabled: true,
                      headerPosition: 'bottom',
                    }"
                    @on-page-change="PageChangePurchases"
                    @on-per-page-change="onPerPageChangePurchases"
                    @on-search="onSearch_purchases"
                    :search-options="{
                      placeholder: $t('Search_this_table'),
                      enabled: true,
                    }"
                    :pagination-options="{
                      enabled: true,
                      mode: 'records',
                      nextLabel: 'next',
                      prevLabel: 'prev',
                      perPageDropdown: [10, 25, 50, 100, 250, 500],
                    }"
                    styleClass="tableOne table-hover vgt-table"
                  >
                  <div slot="table-actions" class="mt-2 mb-3">
                    <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right>
                      <i class="i-Filter-2"></i>
                      {{ $t("Filter") }}
                    </b-button>

                  </div>
                    <template slot="table-row" slot-scope="props">
                      <div v-if="props.column.field == 'Ref'">
                        <router-link
                          :to="'/app/purchases/detail/'+props.row.purchase_id"
                        >
                          <span class="ul-btn__text ml-1">{{props.row.Ref}}</span>
                        </router-link>
                      </div>

                      <div v-else-if="props.column.field == 'total'">
                        <span>{{currentUser.currency}} {{props.row.total}}</span>
                      </div>

                    </template>
                  </vue-good-table>
                </b-tab>                
              </b-col>
            </b-row>

            <!-- Sales Returns Table -->
            <b-row v-if="!isLoading">
              <b-col md="12">
                <b-tab :title="$t('Sales_Returns')">
                    <vue-good-table
                      mode="remote"
                      :columns="columns_sales_returns"
                      :totalRows="totalRows_sales_returns"
                      :rows="rows_sales_returns"
                      :group-options="{
                        enabled: true,
                        headerPosition: 'bottom',
                      }"
                      @on-page-change="PageChangeSalesReturns"
                      @on-per-page-change="onPerPageChangeSalesReturns"
                      @on-search="onSearch_sales_returns"
                      :search-options="{
                        placeholder: $t('Search_this_table'),
                        enabled: true,
                      }"
                      :pagination-options="{
                        enabled: true,
                        mode: 'records',
                        nextLabel: 'next',
                        prevLabel: 'prev',
                        perPageDropdown: [10, 25, 50, 100, 250, 500],
                      }"
                      styleClass="tableOne table-hover vgt-table"
                    >
                    <div slot="table-actions" class="mt-2 mb-3">
                      <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right>
                        <i class="i-Filter-2"></i>
                        {{ $t("Filter") }}
                      </b-button>

                    </div>
                      <template slot="table-row" slot-scope="props">
                        <div v-if="props.column.field == 'Ref'">
                          <router-link
                            :to="'/app/sales_returns/detail/'+props.row.sale_return_id"
                          >
                            <span class="ul-btn__text ml-1">{{props.row.Ref}}</span>
                          </router-link>
                        </div>

                        <div v-else-if="props.column.field == 'total'">
                          <span>{{currentUser.currency}} {{props.row.total}}</span>
                        </div>

                      </template>
                    </vue-good-table>
                </b-tab>
              </b-col>
            </b-row>

            <!-- Purchase Returns Table -->
            <b-row v-if="!isLoading">
              <b-col md="12">                
                <b-tab :title="$t('Purchase_Returns')">
                  <vue-good-table
                    mode="remote"
                    :columns="columns_purchase_returns"
                    :totalRows="totalRows_purchase_returns"
                    :rows="rows_purchase_returns"
                    :group-options="{
                      enabled: true,
                      headerPosition: 'bottom',
                    }"
                    @on-page-change="PageChangePurchaseReturns"
                    @on-per-page-change="onPerPageChangePurchaseReturns"
                    @on-search="onSearch_purchase_returns"
                    :search-options="{
                      placeholder: $t('Search_this_table'),
                      enabled: true,
                    }"
                    :pagination-options="{
                      enabled: true,
                      mode: 'records',
                      nextLabel: 'next',
                      prevLabel: 'prev',
                      perPageDropdown: [10, 25, 50, 100, 250, 500],
                    }"
                    styleClass="tableOne table-hover vgt-table"
                  >
                  <div slot="table-actions" class="mt-2 mb-3">
                    <b-button variant="outline-info ripple m-1" size="sm" v-b-toggle.sidebar-right>
                      <i class="i-Filter-2"></i>
                      {{ $t("Filter") }}
                    </b-button>

                  </div>
                    <template slot="table-row" slot-scope="props">
                      <div v-if="props.column.field == 'Ref'">
                        <router-link
                          :to="'/app/purchase_returns/detail/'+props.row.purchase_return_id"
                        >
                          <span class="ul-btn__text ml-1">{{props.row.Ref}}</span>
                        </router-link>
                      </div>

                      <div v-else-if="props.column.field == 'total'">
                        <span>{{currentUser.currency}} {{props.row.total}}</span>
                      </div>

                    </template>
                  </vue-good-table>
                </b-tab>                
              </b-col>
            </b-row>

          </b-tabs>
        </b-card>
      </b-col>
    </b-row>

    
    <!-- Sidebar Filter -->
    <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
      <div class="px-3 py-2">
        <b-row>
         
          <!-- Reference -->
          <b-col md="12">
            <b-form-group :label="$t('Reference')">
              <b-form-input label="Reference" :placeholder="$t('Reference')" v-model="Filter_Ref"></b-form-input>
            </b-form-group>
          </b-col>

          <!-- Customer  -->
          <b-col md="12">
            <b-form-group :label="$t('Customer')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Customer')"
                v-model="Filter_Client"
                :options="customers.map(customers => ({label: customers.name, value: customers.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- warehouse -->
          <b-col md="12">
            <b-form-group :label="$t('warehouse')">
              <v-select
                v-model="Filter_warehouse"
                :reduce="label => label.value"
                :placeholder="$t('Choose_Warehouse')"
                :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
              />
            </b-form-group>
          </b-col>

           <!-- Vendeur  -->
          <b-col md="12">
            <b-form-group label="Vendeur">
              <v-select
                :reduce="label => label.value"
                placeholder="Choose Vendeur"
                v-model="Filter_user"
                :options="users.map(users => ({label: users.username, value: users.id}))"
              />
            </b-form-group>
          </b-col>

          <b-col md="6" sm="12">
            <b-button
              @click="Apply_Filter()"
              variant="primary btn-block ripple m-1"
              size="sm"
            >
              <i class="i-Filter-2"></i>
              {{ $t("Filter") }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter()" variant="danger ripple btn-block m-1" size="sm">
              <i class="i-Power-2"></i>
              {{ $t("Reset") }}
            </b-button>
          </b-col>
        </b-row>
      </div>
    </b-sidebar>


  </div>
</template>


<script>
import { mapActions, mapGetters } from "vuex";
import jsPDF from "jspdf";
import "jspdf-autotable";
import DateRangePicker from 'vue2-daterange-picker'
//you need to import the CSS manually
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css'
import moment from 'moment'
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Products Report"
  },
  components: { DateRangePicker },
  data() {
    return {
      totalRows_sales: "",
      totalRows_purchases: "",
      totalRows_sales_returns: "",
      totalRows_purchase_returns: "",
      limit_sales: "10",
      limit_purchases: "10",
      limit_sales_returns: "10",
      limit_purchase_returns: "10",
      sales_page: 1,
      purchases_page: 1,
      sales_returns_page: 1,
      purchase_returns_page: 1,
      search_sales:"",
      search_purchases:"",
      search_sales_returns:"",
      search_purchase_returns:"",

      Filter_Client: "",
      Filter_Provider: "",
      Filter_Ref: "",
      Filter_warehouse: "",
      Filter_user: "",

     
      isLoading: true,
      sales: [],
      purchases: [],
      sales_returns: [],
      purchase_returns: [],
      rows_sales: [{
        children: [],
      }],
      rows_purchases: [{
        children: [],
      }],
      rows_sales_returns: [{
        children: [],
      }],
      rows_purchase_returns: [{
        children: [],
      }],
      summaries: {
        sales: { total_count: 0, total_amount: 0, total_quantity: 0 },
        purchases: { total_count: 0, total_amount: 0, total_quantity: 0 },
        sales_returns: { total_count: 0, total_amount: 0, total_quantity: 0 },
        purchase_returns: { total_count: 0, total_amount: 0, total_quantity: 0 },
      },
      warehouses: [],
      customers: [],
      providers: [],
      users: [],
      product:{},
      today_mode: true,
      startDate: "", 
      endDate: "", 
      dateRange: { 
       startDate: "", 
       endDate: "" 
      }, 
      locale:{ 
          //separator between the two ranges apply
          Label: "Apply", 
          cancelLabel: "Cancel", 
          weekLabel: "W", 
          customRangeLabel: "Custom Range", 
          daysOfWeek: moment.weekdaysMin(), 
          //array of days - see moment documenations for details 
          monthNames: moment.monthsShort(), //array of month names - see moment documenations for details 
          firstDay: 1 //ISO first day of week - see moment documenations for details
        },

    };
  },

  computed: {
    ...mapGetters(["currentUser"]),

    // Summary card totals
    totalSalesCount() {
      return this.summaries.sales.total_count || 0;
    },

    totalSalesAmount() {
      return this.summaries.sales.total_amount || 0;
    },

    totalPurchasesCount() {
      return this.summaries.purchases.total_count || 0;
    },

    totalPurchasesAmount() {
      return this.summaries.purchases.total_amount || 0;
    },

    totalSalesReturnsCount() {
      return this.summaries.sales_returns.total_count || 0;
    },

    totalSalesReturnsAmount() {
      return this.summaries.sales_returns.total_amount || 0;
    },

    totalPurchaseReturnsCount() {
      return this.summaries.purchase_returns.total_count || 0;
    },

    totalPurchaseReturnsAmount() {
      return this.summaries.purchase_returns.total_amount || 0;
    },
   
    columns_sales() {
      return [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left"
        },
         {
          label: this.$t("Created_by"),
          field: "created_by",
          tdClass: "text-left",
          thClass: "text-left",
           sortable: false
        },
        {
          label: this.$t("product_name"),
          field: "product_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Quantity"),
          field: "quantity",
          headerField: this.sumTotalQte,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Price"),
          field: "price",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("SubTotal"),
          field: "total",
          headerField: this.sumTotalAmount,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        
      ];
    },

    columns_purchases() {
      return [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left"
        },
         {
          label: this.$t("Created_by"),
          field: "created_by",
          tdClass: "text-left",
          thClass: "text-left",
           sortable: false
        },
        {
          label: this.$t("product_name"),
          field: "product_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Provider"),
          field: "provider_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Quantity"),
          field: "quantity",
          headerField: this.sumTotalQte,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Price"),
          field: "price",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("SubTotal"),
          field: "total",
          headerField: this.sumTotalAmount,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        
      ];
    },

    columns_sales_returns() {
      return [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left"
        },
         {
          label: this.$t("Created_by"),
          field: "created_by",
          tdClass: "text-left",
          thClass: "text-left",
           sortable: false
        },
        {
          label: this.$t("product_name"),
          field: "product_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Quantity"),
          field: "quantity",
          headerField: this.sumTotalQte,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Price"),
          field: "price",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("SubTotal"),
          field: "total",
          headerField: this.sumTotalAmount,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        
      ];
    },

    columns_purchase_returns() {
      return [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left"
        },
         {
          label: this.$t("Created_by"),
          field: "created_by",
          tdClass: "text-left",
          thClass: "text-left",
           sortable: false
        },
        {
          label: this.$t("product_name"),
          field: "product_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Provider"),
          field: "provider_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Quantity"),
          field: "quantity",
          headerField: this.sumTotalQte,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Price"),
          field: "price",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("SubTotal"),
          field: "total",
          headerField: this.sumTotalAmount,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        
      ];
    },
  },

  methods: {

      //------ Apply Filter
    Apply_Filter() {
      this.Get_Sales(1);
      this.Get_Purchases(1);
      this.Get_Sales_Returns(1);
      this.Get_Purchase_Returns(1);
    },

      //------ Reset Filter
    Reset_Filter() {
      this.search = "";
      this.search_sales = "";
      this.search_purchases = "";
      this.search_sales_returns = "";
      this.search_purchase_returns = "";
      this.Filter_Client = "";
      this.Filter_Provider = "";
      this.Filter_Ref = "";
      this.Filter_warehouse = "";
      this.Filter_user = "";
      this.Get_Sales(1);
      this.Get_Purchases(1);
      this.Get_Sales_Returns(1);
      this.Get_Purchase_Returns(1);
    },

     //----------------------------------- Sales PDF ------------------------------\\
    Sales_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");
      let columns = [
        { title: "Date", dataKey: "date" },
        { title: "Ref", dataKey: "Ref" },
        { title: "Created_by", dataKey: "created_by" },
        { title: "Product Name", dataKey: "product_name" },
        { title: "Client", dataKey: "client_name" },
        { title: "Warehouse", dataKey: "warehouse_name" },
        { title: "Quantity", dataKey: "quantity" },
        { title: "Cost", dataKey: "cost" },
        { title: "Price", dataKey: "price" },
        { title: "SubTotal", dataKey: "total" },
      ];
      pdf.autoTable(columns, self.sales);
      pdf.text("Sale List", 40, 25);
      pdf.save("Sale_List.pdf");
    },

    //------------------------------Formetted Numbers -------------------------\\
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
      return `${value[0]}.${formated}`;
    },


    //--------------------------- Event Page Change -------------\\
    PageChangeSales({ currentPage }) {
      if (this.sales_page !== currentPage) {
        this.Get_Sales(currentPage);
      }
    },

    //--------------------------- Event Page Change -------------\\
    PageChangePurchases({ currentPage }) {
      console.log('tess');
      if (this.purchases_page !== currentPage) {
        this.Get_Purchases(currentPage);
      }
    },

    //--------------------------- Event Page Change Sales Returns -------------\\
    PageChangeSalesReturns({ currentPage }) {
      if (this.sales_returns_page !== currentPage) {
        this.Get_Sales_Returns(currentPage);
      }
    },

    //--------------------------- Event Page Change Purchase Returns -------------\\
    PageChangePurchaseReturns({ currentPage }) {
      if (this.purchase_returns_page !== currentPage) {
        this.Get_Purchase_Returns(currentPage);
      }
    },

    //--------------------------- Limit Page Sales -------------\\
    onPerPageChangeSales({ currentPerPage }) {
      if (this.limit_sales !== currentPerPage) {
        this.limit_sales = currentPerPage;
        this.Get_Sales(1);
      }
    },

    onPerPageChangePurchases({ currentPerPage }) {
      console.log('hola');
      if (this.limit_purchases !== currentPerPage) {
        this.limit_purchases = currentPerPage;
        this.Get_Purchases(1);
      }
    },

    onPerPageChangeSalesReturns({ currentPerPage }) {
      if (this.limit_sales_returns !== currentPerPage) {
        this.limit_sales_returns = currentPerPage;
        this.Get_Sales_Returns(1);
      }
    },

    onPerPageChangePurchaseReturns({ currentPerPage }) {
      if (this.limit_purchase_returns !== currentPerPage) {
        this.limit_purchase_returns = currentPerPage;
        this.Get_Purchase_Returns(1);
      }
    },

    onSearch_sales(value) {
      this.search_sales = value.searchTerm;
      this.Get_Sales(1);
    },

    onSearch_purchases(value) {
      this.search_purchases = value.searchTerm;
      this.Get_Purchases(1);
    },

    onSearch_sales_returns(value) {
      this.search_sales_returns = value.searchTerm;
      this.Get_Sales_Returns(1);
    },

    onSearch_purchase_returns(value) {
      this.search_purchase_returns = value.searchTerm;
      this.Get_Purchase_Returns(1);
    },

    sumTotalQte(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) {
        console.error('Invalid input for Total Amount');
        return 0;
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        let qty = parseFloat(rowObj.children[i].quantity.split(" ")[0]);
        sum += qty;
      }
      return sum.toString() + " pc";
    },

    sumTotalCosts(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) {
        console.error('Invalid input for Cost Amount');
        return 0;
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        console.log('rowObj.children[i].cost', rowObj.children[i])
        let cost = parseFloat(rowObj.children[i].cost);
        sum += cost;
      }
      return sum.toString() + " pc";
    },

    sumTotalAmount(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) {
        console.error('Invalid input for Total Amount');
        return 0;
      }
      let sum_amount = 0;

      for (let i = 0; i < rowObj.children.length; i++) {
        sum_amount += rowObj.children[i].total;
      }
      return sum_amount + ' dh';
    },

    //----------------------------- Submit Date Picker -------------------\\
    Submit_filter_dateRange() {
      var self = this;
      self.startDate =  self.dateRange.startDate.toJSON().slice(0, 10);
      self.endDate = self.dateRange.endDate.toJSON().slice(0, 10);
      self.Get_Sales(1);
    },


    get_data_loaded() {
      var self = this;
      if (self.today_mode) {
        let startDate = new Date("01/01/2000");  // Set start date to "01/01/2000"
        let endDate = new Date();  // Set end date to current date

        self.startDate = startDate.toISOString();
        self.endDate = endDate.toISOString();

        self.dateRange.startDate = startDate.toISOString();
        self.dateRange.endDate = endDate.toISOString();
      }
    },


       //----------------------------------- Get Details Product ------------------------------\\
      showDetails() {
      let id = this.$route.params.id;
      axios
        .get(`get_product_detail/${id}`)
        .then(response => {
          this.product = response.data;
        })
        .catch(response => {
         
        });
    },

    //--------------------------- sale_products_details -------------\\
    Get_Sales(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.get_data_loaded();

      axios
        .get(
          "/report/sale_products_details?page=" +
            page +
            "&Ref=" +
            this.Filter_Ref +
            "&client_id=" +
            this.Filter_Client +
            "&warehouse_id=" +
            this.Filter_warehouse +
            "&user_id=" +
            this.Filter_user +
            "&limit=" +
            this.limit_sales +
            "&to=" +
            this.endDate +
            "&from=" +
            this.startDate +
            "&search=" +
            this.search_sales +
            "&id=" +
            this.$route.params.id
        )
        .then(response => {
          this.sales = response.data.sales;
          this.rows_sales = [{
            children: response.data.sales,
          }];
          if (response.data.summary) {
            this.summaries.sales = response.data.summary;
          }
          this.totalRows_sales = response.data.totalRows;
          this.customers = response.data.customers;
          this.warehouses = response.data.warehouses;
          this.users = response.data.users;

          NProgress.done();
          this.isLoading = false;
          this.today_mode = false;
        })
        .catch(response => {
           NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
            this.today_mode = false;
          }, 500);
        });
    },

    //--------------------------- sale_products_details -------------\\
    Get_Purchases(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.get_data_loaded();
      console.log('test')
      axios
        .get(
          "/report/purchase_products_details?page=" +
            page +
            "&Ref=" +
            this.Filter_Ref +
            "&provider_id=" +
            this.Filter_Provider +
            "&warehouse_id=" +
            this.Filter_warehouse +
            "&user_id=" +
            this.Filter_user +
            "&limit=" +
            this.limit_purchases +
            "&to=" +
            this.endDate +
            "&from=" +
            this.startDate +
            "&search=" +
            this.search_purchases +
            "&id=" +
            this.$route.params.id
        )
        .then(response => {
          this.purchases = response.data.purchases;
          this.rows_purchases = [{
            children: response.data.purchases,
          }];
          if (response.data.summary) {
            this.summaries.purchases = response.data.summary;
          }
          this.totalRows_purchases = response.data.totalRows;
          this.providers = response.data.providers;
          console.log('this.providers', this.providers)
          this.warehouses = response.data.warehouses;
          this.users = response.data.users;

          NProgress.done();
          this.isLoading = false;
          this.today_mode = false;
        })
        .catch(response => {
           NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
            this.today_mode = false;
          }, 500);
        });
    },

    //--------------------------- Get Sales Returns -------------\\
    Get_Sales_Returns(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.get_data_loaded();

      axios
        .get(
          "/report/sale_returns_products_details?page=" +
            page +
            "&Ref=" +
            this.Filter_Ref +
            "&client_id=" +
            this.Filter_Client +
            "&warehouse_id=" +
            this.Filter_warehouse +
            "&user_id=" +
            this.Filter_user +
            "&limit=" +
            this.limit_sales_returns +
            "&to=" +
            this.endDate +
            "&from=" +
            this.startDate +
            "&search=" +
            this.search_sales_returns +
            "&id=" +
            this.$route.params.id
        )
        .then(response => {
          this.sales_returns = response.data.sales_returns;
          this.rows_sales_returns = [{
            children: response.data.sales_returns,
          }];
          if (response.data.summary) {
            this.summaries.sales_returns = response.data.summary;
          }
          this.totalRows_sales_returns = response.data.totalRows;
          this.customers = response.data.customers;
          this.warehouses = response.data.warehouses;
          this.users = response.data.users;

          NProgress.done();
          this.isLoading = false;
          this.today_mode = false;
        })
        .catch(response => {
           NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
            this.today_mode = false;
          }, 500);
        });
    },

    //--------------------------- Get Purchase Returns -------------\\
    Get_Purchase_Returns(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.get_data_loaded();

      axios
        .get(
          "/report/purchase_returns_products_details?page=" +
            page +
            "&Ref=" +
            this.Filter_Ref +
            "&provider_id=" +
            this.Filter_Provider +
            "&warehouse_id=" +
            this.Filter_warehouse +
            "&user_id=" +
            this.Filter_user +
            "&limit=" +
            this.limit_purchase_returns +
            "&to=" +
            this.endDate +
            "&from=" +
            this.startDate +
            "&search=" +
            this.search_purchase_returns +
            "&id=" +
            this.$route.params.id
        )
        .then(response => {
          this.purchase_returns = response.data.purchase_returns;
          this.rows_purchase_returns = [{
            children: response.data.purchase_returns,
          }];
          if (response.data.summary) {
            this.summaries.purchase_returns = response.data.summary;
          }
          this.totalRows_purchase_returns = response.data.totalRows;
          this.providers = response.data.providers;
          this.warehouses = response.data.warehouses;
          this.users = response.data.users;

          NProgress.done();
          this.isLoading = false;
          this.today_mode = false;
        })
        .catch(response => {
           NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
            this.today_mode = false;
          }, 500);
        });
    },
    

  
  }, //end Methods

  //----------------------------- Created function------------------- \\

  created: function() {
    this.showDetails();
    this.Get_Sales(1);
    this.Get_Purchases(1);
    this.Get_Sales_Returns(1);
    this.Get_Purchase_Returns(1);
  }
};
</script>
