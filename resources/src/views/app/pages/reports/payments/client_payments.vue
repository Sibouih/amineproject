<template>
  <div class="main-content">
    <breadcumb :page="$t('ClientPayments')" :folder="$t('Reports')"/>

    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

      <b-col md="12" class="text-center" v-if="!isLoading">
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

    <!-- Filters Section -->
    <b-card class="card-filters" v-if="!isLoading">
      <div class="row">
        <!-- Client Filter -->
        <div class="col-md-6 col-sm-12">
          <b-form-group :label="$t('Customer')">
            <v-select
              v-model="Filter_client"
              :reduce="label => label.value"
              :placeholder="$t('Choose_Customer')"
              :options="clients.map(clients => ({label: clients.name, value: clients.id}))"
            />
          </b-form-group>
        </div>

        <!-- Payment Filter -->
        <div class="col-md-6 col-sm-12">
          <b-form-group :label="$t('ModePaiement')">
            <v-select
              v-model="Filter_Reg"
              :reduce="label => label.value"
              :placeholder="$t('PleaseSelect')"
              :options="
                [
                  {label: 'Cash', value: 'Cash'},
                  {label: 'cheque', value: 'cheque'},
                  {label: 'Western Union', value: 'Western Union'},
                  {label: 'bank transfer', value: 'bank transfer'},
                  {label: 'credit card', value: 'credit card'},
                  {label: 'other', value: 'other'},
                ]"
            />
          </b-form-group>
        </div>

        <!-- Reference Filter -->
        <div class="col-md-6 col-sm-12">
          <b-form-group :label="$t('Reference')">
            <b-form-input
              v-model="Filter_Ref"
              :placeholder="$t('Reference')"
            ></b-form-input>
          </b-form-group>
        </div>        

        <!-- Search Input -->
        <div class="col-md-6 col-sm-12">
          <b-form-group :label="$t('search')">
            <b-form-input
              v-model="search"
              :placeholder="$t('Search')"
            ></b-form-input>
          </b-form-group>
        </div>

        <!-- Filter Button -->
        <div class="col-md-12">
          <b-button variant="primary" size="sm" class="mr-1" @click="getPayments()">
            <i class="i-Filter-2"></i> 
            {{ $t('Filter') }}
          </b-button>
          <b-button variant="danger" size="sm" @click="Reset_Filter">
            <i class="i-Power-2"></i> 
            {{ $t('Reset') }}
          </b-button>
        </div>
      </div>
    </b-card>

    <!-- Totals Cards -->
    <b-row v-if="!isLoading" class="my-4">
      <b-col md="3" sm="6">
        <b-card class="text-center">
          <h4 class="text-info">{{ $t('DirectPayments') }}</h4>
          <h5>{{ directPaymentsCount }} {{ $t('Payments') }}</h5>
          <h6 class="text-success">{{ formatNumber(directPaymentsTotalAmount, 2) }} {{ currentUser.currency }}</h6>
        </b-card>
      </b-col>
      <b-col md="3" sm="6">
        <b-card class="text-center">
          <h4 class="text-primary">{{ $t('GlobalPayments') }}</h4>
          <h5>{{ globalPaymentsCount }} {{ $t('Payments') }}</h5>
          <h6 class="text-success">{{ formatNumber(globalPaymentsTotalAmount, 2) }} {{ currentUser.currency }}</h6>
        </b-card>
      </b-col>      
      <b-col md="3" sm="6">
        <b-card class="text-center">
          <h4 class="text-warning">{{ $t('TotalPayments') }}</h4>
          <h5>{{ totalPaymentsCount }} {{ $t('Payments') }}</h5>
          <h6 class="text-success">{{ formatNumber(totalAmount, 2) }} {{ currentUser.currency }}</h6>
        </b-card>
      </b-col>
      <b-col md="3" sm="6">
        <b-card class="text-center">
          <b-button-group>
            <b-button variant="outline-success" size="sm" @click="Payment_PDF">
              <i class="i-File-Copy"></i> {{ $t("PDF") }} {{ $t("All") }}
            </b-button>
            <b-button variant="outline-primary" size="sm" @click="Export_GlobalPayments">
              <i class="i-File-Copy"></i> {{ $t("GlobalPayments") }}
            </b-button>
            <b-button variant="outline-info" size="sm" @click="Export_DirectPayments">
              <i class="i-File-Copy"></i> {{ $t("DirectPayments") }}
            </b-button>
          </b-button-group>
        </b-card>
      </b-col>
    </b-row>

    <!-- Tabs Section -->
    <b-card class="wrapper" v-if="!isLoading">
      <b-tabs v-model="activeTab" @input="onTabChange">
        <!-- Global Payments Tab -->
        <b-tab :title="$t('GlobalPayments') + ' (' + globalPaymentsCount + ')'" active>
          <b-table
            show-empty
            stacked="md"
            :items="globalPayments"
            :fields="globalPaymentFields"
            :per-page="perPage"
            :current-page="currentPage"
            :total-rows="globalPaymentsCount"
            :sort-by.sync="serverParams.sort.field"
            :sort-desc.sync="serverParams.sort.type"
            :busy="isLoading"
            class="table-sm"
            responsive="sm"
          >
            <template v-slot:cell-Ref="row">
              <span>{{row.item.Ref}}</span>
            </template>

            <template v-slot:cell-date="row">
              <span>{{formatDate(row.item.date)}}</span>
            </template>

            <template v-slot:cell-client_name="row">
              <span>{{row.item.client_name}}</span>
            </template>

            <template v-slot:cell-Reglement="row">
              <span>{{row.item.Reglement}}</span>
            </template>

            <template v-slot:cell-montant="row">
              <span>{{formatNumber(row.item.montant, 2)}} {{currentUser.currency}}</span>
            </template>

            <template #cell(actions)="row">
              <div class="text-left">
                <button type="button" class="btn btn-primary btn-sm mr-1" @click="editGlobalPayment(row.item)">
                  {{ $t('Edit') }}
                </button>
                <button type="button" class="btn btn-danger btn-sm" @click="deleteGlobalPayment(row.item.id)">
                  {{ $t('Delete_payment') }}
                </button>
              </div>
            </template>
          </b-table>
        </b-tab>

        <!-- Direct Payments Tab -->
        <b-tab :title="$t('DirectPayments') + ' (' + directPaymentsCount + ')'">
          <b-table
            show-empty
            stacked="md"
            :items="directPayments"
            :fields="directPaymentFields"
            :per-page="perPage"
            :current-page="currentPage"
            :total-rows="directPaymentsCount"
            :sort-by.sync="serverParams.sort.field"
            :sort-desc.sync="serverParams.sort.type"
            :busy="isLoading"
            class="table-sm"
            responsive="sm"
          >
            <template v-slot:cell-Ref="row">
              <span>{{row.item.Ref}}</span>
            </template>

            <template v-slot:cell-date="row">
              <span>{{formatDate(row.item.date)}}</span>
            </template>

            <template v-slot:cell-client_name="row">
              <span>{{row.item.client_name}}</span>
            </template>

            <template v-slot:cell-sale_ref="row">
              <span>{{row.item.sale_ref}}</span>
            </template>

            <template v-slot:cell-Reglement="row">
              <span>{{row.item.Reglement}}</span>
            </template>

            <template v-slot:cell-montant="row">
              <span>{{formatNumber(row.item.montant, 2)}} {{currentUser.currency}}</span>
            </template>

            <template #cell(actions)="row">
              <div class="text-left">
                <button type="button" class="btn btn-primary btn-sm mr-1" @click="editDirectPayment(row.item)">
                  {{ $t('Edit') }}
                </button>
                <button type="button" class="btn btn-danger btn-sm" @click="deleteDirectPayment(row.item.id)">
                  {{ $t('Delete_payment') }}
                </button>
              </div>
            </template>
          </b-table>
        </b-tab>
      </b-tabs>

      <!-- Global Payments Pagination -->
      <b-row v-if="activeTab === 0">
        <b-col md="6" class="mt-4">
          <p>{{ $t('Showing') }} {{ globalFirstItem }} {{ $t('to') }} {{ globalLastItem }} {{ $t('of') }} {{ globalPaymentsCount }} {{ $t('entries') }}</p>
        </b-col>
        <b-col md="6" class="mt-4">
          <b-pagination
            v-model="globalCurrentPage"
            :total-rows="globalPaymentsCount"
            :per-page="globalPerPage"
            class="my-0 pagination-sm justify-content-end"
            @change="getGlobalPayments"
          ></b-pagination>
        </b-col>
      </b-row>

      <!-- Direct Payments Pagination -->
      <b-row v-if="activeTab === 1">
        <b-col md="6" class="mt-4">
          <p>{{ $t('Showing') }} {{ directFirstItem }} {{ $t('to') }} {{ directLastItem }} {{ $t('of') }} {{ directPaymentsCount }} {{ $t('entries') }}</p>
        </b-col>
        <b-col md="6" class="mt-4">
          <b-pagination
            v-model="directCurrentPage"
            :total-rows="directPaymentsCount"
            :per-page="directPerPage"
            class="my-0 pagination-sm justify-content-end"
            @change="getDirectPayments"
          ></b-pagination>
        </b-col>
      </b-row>
    </b-card>

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

          <!-- Customers  -->
          <b-col md="12">
            <b-form-group :label="$t('Customer')">
              <v-select
                :reduce="label => label.value"
                :placeholder="$t('Choose_Customer')"
                v-model="Filter_client"
                :options="clients.map(clients => ({label: clients.name, value: clients.id}))"
              />
            </b-form-group>
          </b-col>

          <!-- Payment choice -->
          <b-col md="12">
            <b-form-group :label="$t('ModePaiement')">
              <v-select
                v-model="Filter_Reg"
                :reduce="label => label.value"
                :placeholder="$t('PleaseSelect')"
                :options="
                          [
                          {label: 'Cash', value: 'Cash'},
                          {label: 'cheque', value: 'cheque'},
                          {label: 'TPE', value: 'tpe'},
                          {label: 'Western Union', value: 'Western Union'},
                          {label: 'bank transfer', value: 'bank transfer'},
                          {label: 'credit card', value: 'credit card'},
                          {label: 'other', value: 'other'},
                          ]"
              ></v-select>
            </b-form-group>
          </b-col>          

          <b-col md="6" sm="12">
            <b-button
              @click="getPayments()"
              variant="primary ripple m-1"
              size="sm"
              block
            >
              <i class="i-Filter-2"></i>
              {{ $t("Filter") }}
            </b-button>
          </b-col>
          <b-col md="6" sm="12">
            <b-button @click="Reset_Filter()" variant="danger ripple m-1" size="sm" block>
              <i class="i-Power-2"></i>
              {{ $t("Reset") }}
            </b-button>
          </b-col>
        </b-row>
      </div>
    </b-sidebar>

    <!-- Edit Global Payment Modal -->
    <b-modal 
      v-model="showEditGlobalModal" 
      :title="$t('EditGlobalPayment')" 
      size="lg" 
      @hidden="resetEditGlobalForm"
    >
      <b-form @submit.prevent="updateGlobalPayment">
        <b-row>
          <b-col md="6">
            <b-form-group :label="$t('Reference')">
              <b-form-input 
                v-model="editGlobalForm.Ref" 
                :placeholder="$t('Reference')"
                required
              />
            </b-form-group>
          </b-col>
          <b-col md="6">
            <b-form-group :label="$t('date')">
              <b-form-input 
                v-model="editGlobalForm.date" 
                type="date"
                required
              />
            </b-form-group>
          </b-col>
        </b-row>
        
        <b-row>
          <b-col md="6">
            <b-form-group :label="$t('Customer')">
              <v-select
                v-model="editGlobalForm.client_id"
                :reduce="label => label.value"
                :placeholder="$t('Choose_Customer')"
                :options="clients.map(client => ({label: client.name, value: client.id}))"
                required
              />
            </b-form-group>
          </b-col>
          <b-col md="6">
            <b-form-group :label="$t('Amount')">
              <b-form-input 
                v-model="editGlobalForm.montant" 
                type="number"
                step="0.01"
                :placeholder="$t('Amount')"
                required
              />
            </b-form-group>
          </b-col>
        </b-row>
        
        <b-row>
          <b-col md="6">
            <b-form-group :label="$t('ModePaiement')">
              <v-select
                v-model="editGlobalForm.Reglement"
                :placeholder="$t('PleaseSelect')"
                :options="[
                  {label: 'Cash', value: 'Cash'},
                  {label: 'cheque', value: 'cheque'},
                  {label: 'Western Union', value: 'Western Union'},
                  {label: 'bank transfer', value: 'bank transfer'},
                  {label: 'credit card', value: 'credit card'},
                  {label: 'other', value: 'other'}
                ]"
                required
              />
            </b-form-group>
          </b-col>
          <b-col md="6">
            <b-form-group :label="$t('Note')">
              <b-form-textarea 
                v-model="editGlobalForm.notes" 
                :placeholder="$t('Note')"
                rows="3"
              />
            </b-form-group>
          </b-col>
        </b-row>
      </b-form>
      
      <template v-slot:modal-footer>
        <b-button variant="secondary" @click="showEditGlobalModal = false">
          {{ $t('Cancel') }}
        </b-button>
        <b-button variant="primary" @click="updateGlobalPayment" :disabled="isUpdating">
          <span v-if="isUpdating">
            <b-spinner small></b-spinner> {{ $t('Updating') }}...
          </span>
          <span v-else>
            {{ $t('Update_payment') }}
          </span>
        </b-button>
      </template>
    </b-modal>

    <!-- Edit Direct Payment Modal -->
    <b-modal 
      v-model="showEditDirectModal" 
      :title="$t('EditDirectPayment')" 
      size="lg" 
      @hidden="resetEditDirectForm"
    >
      <b-form @submit.prevent="updateDirectPayment">
        <b-row>
          <b-col md="6">
            <b-form-group :label="$t('Reference')">
              <b-form-input 
                v-model="editDirectForm.Ref" 
                :placeholder="$t('Reference')"
                required
              />
            </b-form-group>
          </b-col>
          <b-col md="6">
            <b-form-group :label="$t('date')">
              <b-form-input 
                v-model="editDirectForm.date" 
                type="date"
                required
              />
            </b-form-group>
          </b-col>
        </b-row>
        
        <b-row>
          <b-col md="6">
            <b-form-group :label="$t('Customer')">
              <b-form-input 
                v-model="editDirectForm.client_name" 
                :placeholder="$t('Customer')"
                readonly
              />
            </b-form-group>
          </b-col>
          <b-col md="6">
            <b-form-group :label="$t('SaleRef')">
              <b-form-input 
                v-model="editDirectForm.sale_ref" 
                :placeholder="$t('SaleRef')"
                readonly
              />
            </b-form-group>
          </b-col>
        </b-row>
        
        <b-row>
          <b-col md="6">
            <b-form-group :label="$t('Amount')">
              <b-form-input 
                v-model="editDirectForm.montant" 
                type="number"
                step="0.01"
                :placeholder="$t('Amount')"
                required
              />
            </b-form-group>
          </b-col>
          <b-col md="6">
            <b-form-group :label="$t('ModePaiement')">
              <v-select
                v-model="editDirectForm.Reglement"
                :placeholder="$t('PleaseSelect')"
                :options="[
                  {label: 'Cash', value: 'Cash'},
                  {label: 'cheque', value: 'cheque'},
                  {label: 'Western Union', value: 'Western Union'},
                  {label: 'bank transfer', value: 'bank transfer'},
                  {label: 'credit card', value: 'credit card'},
                  {label: 'other', value: 'other'}
                ]"
                required
              />
            </b-form-group>
          </b-col>
        </b-row>
        
        <b-row>
          <b-col md="12">
            <b-form-group :label="$t('Note')">
              <b-form-textarea 
                v-model="editDirectForm.notes" 
                :placeholder="$t('Note')"
                rows="3"
              />
            </b-form-group>
          </b-col>
        </b-row>
      </b-form>
      
      <template v-slot:modal-footer>
        <b-button variant="secondary" @click="showEditDirectModal = false">
          {{ $t('Cancel') }}
        </b-button>
        <b-button variant="primary" @click="updateDirectPayment" :disabled="isUpdating">
          <span v-if="isUpdating">
            <b-spinner small></b-spinner> {{ $t('Updating') }}...
          </span>
          <span v-else>
            {{ $t('Update') }}
          </span>
        </b-button>
      </template>
    </b-modal>

  </div>
</template>

<script>
import NProgress from "nprogress";
import jsPDF from "jspdf";
import "jspdf-autotable";
import axios from "axios";
import { mapGetters } from "vuex";
import DateRangePicker from 'vue2-daterange-picker';
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css';
import moment from 'moment';

export default {
  metaInfo: {
    title: "Client Payments Report"
  },
  components: { DateRangePicker },

  data() {
    return {
      isLoading: true,
      activeTab: 0,
      serverParams: {
        sort: {
          field: "id",
          type: false,
        },
        page: 1,
        perPage: 10
      },
      limit: "10",
      totalRows: "",
      search: "",
      Filter_client: "",
      Filter_Ref: "",
      Filter_Reg: "",
      startDate: "",
      endDate: "",
      payments: [],
      globalPayments: [],
      directPayments: [],
      clients: [],
      sales: [],
      globalPaymentsCount: 0,
      directPaymentsCount: 0,
      globalPaymentsTotalAmount: 0,
      directPaymentsTotalAmount: 0,
      totalAmount: 0,
      // Separate pagination for each tab
      globalCurrentPage: 1,
      globalPerPage: 10,
      directCurrentPage: 1,
      directPerPage: 10,
      // Modal states
      showEditGlobalModal: false,
      showEditDirectModal: false,
      isUpdating: false,
      // Edit forms
      editGlobalForm: {
        id: null,
        Ref: '',
        date: '',
        client_id: null,
        montant: '',
        Reglement: '',
        notes: ''
      },
      editDirectForm: {
        id: null,
        Ref: '',
        date: '',
        client_name: '',
        sale_ref: '',
        montant: '',
        Reglement: '',
        notes: ''
      },
      globalPaymentFields: [
        {
          key: "date",
          label: this.$t("date"),
          sortable: true,
          tdClass: "text-left"
        },
        {
          key: "Ref",
          label: this.$t("Reference"),
          sortable: true,
          tdClass: "text-left"
        },
        {
          key: "client_name",
          label: this.$t("CustomerName"),
          sortable: false,
          tdClass: "text-left"
        },
        {
          key: "Reglement",
          label: this.$t("ModePaiement"),
          sortable: true,
          tdClass: "text-left"
        },
        {
          key: "montant",
          label: this.$t("Amount"),
          sortable: true,
          tdClass: "text-left"
        },
        {
          key: "actions",
          label: this.$t("Action"),
          sortable: false,
          tdClass: "text-center"
        }
      ],
      directPaymentFields: [
        {
          key: "date",
          label: this.$t("date"),
          sortable: true,
          tdClass: "text-left"
        },
        {
          key: "Ref",
          label: this.$t("Reference"),
          sortable: true,
          tdClass: "text-left"
        },
        {
          key: "client_name",
          label: this.$t("CustomerName"),
          sortable: false,
          tdClass: "text-left"
        },
        {
          key: "sale_ref",
          label: this.$t("SaleRef"),
          sortable: false,
          tdClass: "text-left"
        },
        {
          key: "Reglement",
          label: this.$t("ModePaiement"),
          sortable: true,
          tdClass: "text-left"
        },
        {
          key: "montant",
          label: this.$t("Amount"),
          sortable: true,
          tdClass: "text-left"
        },
        {
          key: "actions",
          label: this.$t("Action"),
          sortable: false,
          tdClass: "text-center"
        }
      ],
      fields: [
        {
          key: "date",
          label: this.$t("date"),
          sortable: true,
          tdClass: "text-left"
        },
        {
          key: "Ref",
          label: this.$t("Reference"),
          sortable: true,
          tdClass: "text-left"
        },
        {
          key: "client_name",
          label: this.$t("CustomerName"),
          sortable: false,
          tdClass: "text-left"
        },
        {
          key: "Reglement",
          label: this.$t("ModePaiement"),
          sortable: true,
          tdClass: "text-left"
        },
        {
          key: "montant",
          label: this.$t("Amount"),
          sortable: true,
          tdClass: "text-left"
        },        
      ],      
      today_mode: true,
      dateRange: { 
       startDate: "", 
       endDate: "" 
      }, 
      locale:{ 
          Label: "Apply", 
          cancelLabel: "Cancel", 
          weekLabel: "W", 
          customRangeLabel: "Custom Range", 
          daysOfWeek: moment.weekdaysMin(),
          monthNames: moment.monthsShort(),
          firstDay: 1
        },
      currentPage: 1,
      perPage: 10,
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    
    // Get total items for display
    totalPaymentsCount() {
      return this.globalPaymentsCount + this.directPaymentsCount;
    },
    firstItem() {
      const firstItem = (this.currentPage - 1) * this.perPage + 1;
      return this.totalRows ? firstItem : 0;
    },
    
    // Get last item for display
    lastItem() {
      const lastItem = this.currentPage * this.perPage;
      return this.totalRows ? (lastItem > this.totalRows ? this.totalRows : lastItem) : 0;
    },

    // Global Payments Pagination
    globalFirstItem() {
      const firstItem = (this.globalCurrentPage - 1) * this.globalPerPage + 1;
      return this.globalPaymentsCount ? firstItem : 0;
    },
    globalLastItem() {
      const lastItem = this.globalCurrentPage * this.globalPerPage;
      return this.globalPaymentsCount ? (lastItem > this.globalPaymentsCount ? this.globalPaymentsCount : lastItem) : 0;
    },

    // Direct Payments Pagination
    directFirstItem() {
      const firstItem = (this.directCurrentPage - 1) * this.directPerPage + 1;
      return this.directPaymentsCount ? firstItem : 0;
    },
    directLastItem() {
      const lastItem = this.directCurrentPage * this.directPerPage;
      return this.directPaymentsCount ? (lastItem > this.directPaymentsCount ? this.directPaymentsCount : lastItem) : 0;
    },
    
    // Get locale for date picker
    locale() {
      return {
        ...this.$t("datetimepicker"),
        cancelLabel: this.$t("Cancel"),
        applyLabel: this.$t("apply"),
      };
    }
  },
  methods: {
    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.getPayments();
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit === currentPerPage) {
        return;
      }
      this.limit = currentPerPage;
      this.updateParams({ perPage: currentPerPage });
      this.getPayments();
    },

    //---- Event Sort Change
    onSortChange(params) {
      let field = "";
      if (params[0].field === "client_name") {
        field = "client_id";
      } else {
        field = params[0].field;
      }
      this.updateParams({
        sort: {
          type: params[0].type === "desc" ? "desc" : "asc",
          field: field
        }
      });
      this.getPayments();
    },

    //---- Event Search
    onSearch(value) {
      this.search = value.searchTerm;
      // Reset pagination for both tabs
      this.globalCurrentPage = 1;
      this.directCurrentPage = 1;
      this.getPayments();
    },

    //---- Reset Filter
    Reset_Filter() {
      this.search = "";
      this.Filter_client = "";
      this.Filter_Ref = "";
      this.Filter_Reg = "";
      this.startDate = "";
      this.endDate = "";
      // Reset pagination for both tabs
      this.globalCurrentPage = 1;
      this.directCurrentPage = 1;
      this.getPayments();
    },

    // Submit Filter Date Range
    Submit_filter_dateRange() {
      this.startDate = this.dateRange.startDate;
      this.endDate = this.dateRange.endDate;
      // Reset pagination for both tabs
      this.globalCurrentPage = 1;
      this.directCurrentPage = 1;
      this.getPayments();
    },

    // Format Date
    formatDate(d) {
      if (!d) return '';
      // Make sure d is a string
      const dateStr = String(d);
      if (!dateStr.includes('-')) {
        // If it's not in expected format, try to convert from timestamp or return as is
        try {
          const date = new Date(d);
          if (!isNaN(date.getTime())) {
            return date.toISOString().slice(0, 10); // YYYY-MM-DD format
          }
        } catch (e) {
          // If date conversion fails, return original string
          return dateStr;
        }
        return dateStr;
      }
      
      var m1 = dateStr.split('-')[0];
      var m2 = dateStr.split('-')[1];
      var m3 = dateStr.split('-')[2];
      return [m1, m2, m3].join('-');
    },

    // Format Numbers to 2 decimals
    formatNumber(number, dec) {
      const value = (typeof number === "string"
        ? number
        : number.toString()
      ).split(".");
      if (dec <= 0) return value[0];
      let formattedNumber = value[0] + "." + (value[1] || "").padEnd(dec, "0");
      return Number(formattedNumber).toFixed(dec);
    },

    //---------------------------------- PDF ----------------------------------\\
    PDF_Payment(payment) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      
      axios
        .get(`Payment_sale_PDF/${payment.id}`, {
          responseType: "blob", // important
          headers: {
            "Content-Type": "application/json"
          }
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", "Payment_" + payment.Ref + ".pdf");
          document.body.appendChild(link);
          link.click();
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
        })
        .catch(() => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
        });
    },

    //------------------------ Payment PDF ------------------------------\\
    Payment_PDF() {
      var self = this;

      // Start the progress bar
      NProgress.start();
      NProgress.set(0.1);

      // Fetch all payments data for export (not paginated)
      Promise.all([
        // Get all global payments
        axios.get("report/client_payments_report", {
          params: {
            page: 1,
            SortField: this.serverParams.sort.field,
            SortType: this.serverParams.sort.type,
            search: this.search,
            limit: -1, // Get all data
            client_id: this.Filter_client,
            Ref: this.Filter_Ref,
            Reglement: this.Filter_Reg,
            from: this.startDate ? this.startDate : "",
            to: this.endDate ? this.endDate : "",
            payment_type: 'global'
          }
        }),
        // Get all direct payments
        axios.get("report/client_payments_report", {
          params: {
            page: 1,
            SortField: this.serverParams.sort.field,
            SortType: this.serverParams.sort.type,
            search: this.search,
            limit: -1, // Get all data
            client_id: this.Filter_client,
            Ref: this.Filter_Ref,
            Reglement: this.Filter_Reg,
            from: this.startDate ? this.startDate : "",
            to: this.endDate ? this.endDate : "",
            payment_type: 'direct'
          }
        })
      ]).then(responses => {
        const globalPaymentsData = responses[0].data.globalPayments || [];
        const directPaymentsData = responses[1].data.directPayments || [];
        
        // Get all filtered payments (both global and direct)
        const allPayments = [...globalPaymentsData, ...directPaymentsData];
        
        // Sort payments by date (most recent first)
        const sortedPayments = allPayments.sort((a, b) => new Date(b.date) - new Date(a.date));

        // Check if there are payments to export
        if (sortedPayments.length === 0) {
          NProgress.done();
          this.$swal({
            title: this.$t('NoDataToExport'),
            text: this.$t('NoPaymentsFoundForExport'),
            icon: 'warning',
            confirmButtonText: this.$t('Ok')
          });
          return;
        }

        // Generate PDF with all data
        this.generateAllPaymentsPDF(sortedPayments, globalPaymentsData.length, directPaymentsData.length, 
          responses[0].data.globalPaymentsTotalAmount || 0, responses[1].data.directPaymentsTotalAmount || 0);
        
        NProgress.done();
      }).catch(error => {
        console.error('Error fetching payment data for export:', error);
        NProgress.done();
        this.$swal({
          title: this.$t('Error'),
          text: this.$t('SomethingWentWrong'),
          icon: 'error',
          confirmButtonText: this.$t('Ok')
        });
      });
    },

    //------------------------ Generate All Payments PDF ------------------------------\\
    generateAllPaymentsPDF(sortedPayments, globalCount, directCount, globalTotal, directTotal) {
      var self = this;

      let pdf = new jsPDF("p", "pt");
      
      // Set document properties
      pdf.setProperties({
        title: "Rapport des paiements des clients"
      });
      
      // Add styled title
      let pageWidth = pdf.internal.pageSize.getWidth();
      let title = "Rapport des paiements des clients";
      
      // Title styling
      pdf.setFillColor(240, 240, 240);
      pdf.rect(0, 0, pageWidth, 60, 'F');
      pdf.setDrawColor(200, 200, 200);
      pdf.setLineWidth(0.5);
      pdf.line(0, 60, pageWidth, 60);
      
      pdf.setFontSize(18);
      pdf.setFont("helvetica", "bold");
      let titleWidth = pdf.getStringUnitWidth(title) * 18;
      let titleX = (pageWidth - titleWidth) / 2;
      pdf.text(title, titleX, 35);
      
      // Reset font for regular text
      pdf.setFont("helvetica", "normal");
      pdf.setFontSize(11);
      
      // Date information with proper spacing
      const now = new Date();
      const today = `${now.getDate().toString().padStart(2, '0')}/${(now.getMonth()+1).toString().padStart(2, '0')}/${now.getFullYear()} ${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;

      let yPos = 80;    

      pdf.text("Date d'impression: " + today, 40, yPos);
      yPos += 10;
      
      // Reset font for table
      pdf.setFont("helvetica", "normal");
      pdf.setFontSize(11);
      
      // Define table columns with better formatting
      let columns = [
        { title: "Date", dataKey: "date" },
        { title: "Référence", dataKey: "Ref" },
        { title: "Client", dataKey: "client_name" },
        { title: "Type", dataKey: "payment_type" },
        { title: "Mode de paiement", dataKey: "Reglement" },
        { title: "Montant", dataKey: "amount" }
      ];
      
      // Simple table styling with borders
      pdf.autoTable(columns, sortedPayments.map(item => {
        return {
          date: item.date,
          Ref: item.Ref,
          client_name: item.client_name,
          payment_type: item.payment_type === 'global' ? 'Global' : 'Direct',
          Reglement: item.Reglement,
          amount: self.formatNumber(item.montant, 2) + ' ' + self.currentUser.currency
        };
      }), {
        startY: yPos + 10,
        margin: { top: yPos + 10, right: 40, bottom: 40, left: 40 },
        headStyles: { 
          fillColor: [220, 220, 220],
          textColor: [0, 0, 0],
          fontStyle: 'bold',
          lineWidth: 0.5,
          lineColor: [80, 80, 80]
        },
        bodyStyles: { 
          lineWidth: 0.5,
          lineColor: [80, 80, 80]
        },
        styles: {
          cellPadding: 4,
          fontSize: 10
        },
        tableLineWidth: 0.5,
        tableLineColor: [80, 80, 80],
        drawCell: function(cell, data) {
          // Add border to each cell
          var doc = pdf;
          var color = [80, 80, 80];
          var lineWidth = 0.5;
          
          doc.setDrawColor(color[0], color[1], color[2]);
          doc.setLineWidth(lineWidth);
          
          // Draw cell border
          doc.rect(cell.x, cell.y, cell.width, cell.height);
        }
      });
      
      // Add summary information
      const finalY = pdf.lastAutoTable.finalY || yPos + 200;
      const summaryStartY = finalY + 30;
      
      pdf.setFontSize(12);
      pdf.setFont("helvetica", "bold");
      pdf.text("Résumé:", 40, summaryStartY);
      
      pdf.setFontSize(10);
      pdf.setFont("helvetica", "normal");
      pdf.text(`Total des paiements: ${sortedPayments.length}`, 40, summaryStartY + 20);
      pdf.text(`Paiements globaux: ${globalCount} (${self.formatNumber(globalTotal, 2)} ${self.currentUser.currency})`, 40, summaryStartY + 35);
      pdf.text(`Paiements directs: ${directCount} (${self.formatNumber(directTotal, 2)} ${self.currentUser.currency})`, 40, summaryStartY + 50);
      pdf.text(`Montant total: ${self.formatNumber(globalTotal + directTotal, 2)} ${self.currentUser.currency}`, 40, summaryStartY + 65);
      
      // Add page numbers
      const pageCount = pdf.internal.getNumberOfPages();
      for (let i = 1; i <= pageCount; i++) {
        pdf.setPage(i);
        pdf.setFontSize(10);
        pdf.text(`Page ${i}/${pageCount}`, pageWidth - 60, pdf.internal.pageSize.getHeight() - 30);
      }
      
      // Generate filename with date
      const fileDate = `${now.getDate().toString().padStart(2, '0')}-${(now.getMonth()+1).toString().padStart(2, '0')}-${now.getFullYear()}_${now.getHours().toString().padStart(2, '0')}h${now.getMinutes().toString().padStart(2, '0')}`;
      const filename = `Paiements_Clients_${fileDate}.pdf`;
      
      pdf.save(filename);
    },

    //------------------------ Export Global Payments PDF ------------------------------\\
    Export_GlobalPayments() {
      var self = this;

      // Start the progress bar
      NProgress.start();
      NProgress.set(0.1);

      // Fetch all global payments data for export (not paginated)
      axios.get("report/client_payments_report", {
        params: {
          page: 1,
          SortField: this.serverParams.sort.field,
          SortType: this.serverParams.sort.type,
          search: this.search,
          limit: -1, // Get all data
          client_id: this.Filter_client,
          Ref: this.Filter_Ref,
          Reglement: this.Filter_Reg,
          from: this.startDate ? this.startDate : "",
          to: this.endDate ? this.endDate : "",
          payment_type: 'global'
        }
      }).then(response => {
        const globalPaymentsData = response.data.globalPayments || [];

        // Check if there are global payments to export
        if (globalPaymentsData.length === 0) {
          NProgress.done();
          this.$swal({
            title: this.$t('NoDataToExport'),
            text: this.$t('NoGlobalPaymentsFoundForExport'),
            icon: 'warning',
            confirmButtonText: this.$t('Ok')
          });
          return;
        }

        // Sort global payments by date (most recent first)
        const sortedPayments = [...globalPaymentsData].sort((a, b) => new Date(b.date) - new Date(a.date));

        this.generatePDF(sortedPayments, 'Rapport des paiements globaux', 'Paiements_Globaux');
        NProgress.done();
      }).catch(error => {
        console.error('Error fetching global payments for export:', error);
        NProgress.done();
        this.$swal({
          title: this.$t('Error'),
          text: this.$t('SomethingWentWrong'),
          icon: 'error',
          confirmButtonText: this.$t('Ok')
        });
      });
    },

    //------------------------ Export Direct Payments PDF ------------------------------\\
    Export_DirectPayments() {
      var self = this;

      // Start the progress bar
      NProgress.start();
      NProgress.set(0.1);

      // Fetch all direct payments data for export (not paginated)
      axios.get("report/client_payments_report", {
        params: {
          page: 1,
          SortField: this.serverParams.sort.field,
          SortType: this.serverParams.sort.type,
          search: this.search,
          limit: -1, // Get all data
          client_id: this.Filter_client,
          Ref: this.Filter_Ref,
          Reglement: this.Filter_Reg,
          from: this.startDate ? this.startDate : "",
          to: this.endDate ? this.endDate : "",
          payment_type: 'direct'
        }
      }).then(response => {
        const directPaymentsData = response.data.directPayments || [];

        // Check if there are direct payments to export
        if (directPaymentsData.length === 0) {
          NProgress.done();
          this.$swal({
            title: this.$t('NoDataToExport'),
            text: this.$t('NoDirectPaymentsFoundForExport'),
            icon: 'warning',
            confirmButtonText: this.$t('Ok')
          });
          return;
        }

        // Sort direct payments by date (most recent first)
        const sortedPayments = [...directPaymentsData].sort((a, b) => new Date(b.date) - new Date(a.date));

        this.generatePDF(sortedPayments, 'Rapport des paiements directs', 'Paiements_Directs');
        NProgress.done();
      }).catch(error => {
        console.error('Error fetching direct payments for export:', error);
        NProgress.done();
        this.$swal({
          title: this.$t('Error'),
          text: this.$t('SomethingWentWrong'),
          icon: 'error',
          confirmButtonText: this.$t('Ok')
        });
      });
    },

    //------------------------ Generate PDF (Common method) ------------------------------\\
    generatePDF(payments, title, filenamePrefix) {
      var self = this;

      let pdf = new jsPDF("p", "pt");
      
      // Set document properties
      pdf.setProperties({
        title: title
      });
      
      // Add styled title
      let pageWidth = pdf.internal.pageSize.getWidth();
      
      // Title styling
      pdf.setFillColor(240, 240, 240);
      pdf.rect(0, 0, pageWidth, 60, 'F');
      pdf.setDrawColor(200, 200, 200);
      pdf.setLineWidth(0.5);
      pdf.line(0, 60, pageWidth, 60);
      
      pdf.setFontSize(18);
      pdf.setFont("helvetica", "bold");
      let titleWidth = pdf.getStringUnitWidth(title) * 18;
      let titleX = (pageWidth - titleWidth) / 2;
      pdf.text(title, titleX, 35);
      
      // Reset font for regular text
      pdf.setFont("helvetica", "normal");
      pdf.setFontSize(11);
      
      // Date information with proper spacing
      const now = new Date();
      const today = `${now.getDate().toString().padStart(2, '0')}/${(now.getMonth()+1).toString().padStart(2, '0')}/${now.getFullYear()} ${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}`;
      
      let yPos = 80;    
      
      pdf.text("Date d'impression: " + today, 40, yPos);
      yPos += 20;
      
      // Add filter information
      pdf.setFontSize(10);
      pdf.setFont("helvetica", "bold");
      pdf.text("Filtres appliqués:", 40, yPos);
      yPos += 15;
      
      pdf.setFont("helvetica", "normal");
      if (this.Filter_client) {
        const clientName = this.clients.find(c => c.id == this.Filter_client)?.name || 'N/A';
        pdf.text("• Client: " + clientName, 50, yPos);
        yPos += 12;
      }
      if (this.Filter_Ref) {
        pdf.text("• Référence: " + this.Filter_Ref, 50, yPos);
        yPos += 12;
      }
      if (this.Filter_Reg) {
        pdf.text("• Mode de paiement: " + this.Filter_Reg, 50, yPos);
        yPos += 12;
      }
      if (this.startDate) {
        pdf.text("• Date de début: " + this.startDate, 50, yPos);
        yPos += 12;
      }
      if (this.endDate) {
        pdf.text("• Date de fin: " + this.endDate, 50, yPos);
        yPos += 12;
      }
      if (this.search) {
        pdf.text("• Recherche: " + this.search, 50, yPos);
        yPos += 12;
      }
      
      // Add some spacing
      yPos += 10;
      
      // Reset font for table
      pdf.setFont("helvetica", "normal");
      pdf.setFontSize(11);
      
      // Define table columns
      let columns = [
        { title: "Date", dataKey: "date" },
        { title: "Référence", dataKey: "Ref" },
        { title: "Client", dataKey: "client_name" },
        { title: "Mode de paiement", dataKey: "Reglement" },
        { title: "Montant", dataKey: "amount" }
      ];
      
      // Add sale reference column for direct payments
      if (payments.length > 0 && payments[0].payment_type === 'direct') {
        columns.splice(3, 0, { title: "Réf. Vente", dataKey: "sale_ref" });
      }
      
      // Simple table styling with borders
      pdf.autoTable(columns, payments.map(item => {
        let row = {
          date: item.date,
          Ref: item.Ref,
          client_name: item.client_name,
          Reglement: item.Reglement,
          amount: self.formatNumber(item.montant, 2) + ' ' + self.currentUser.currency
        };
        
        // Add sale reference for direct payments
        if (item.payment_type === 'direct' && item.sale_ref) {
          row.sale_ref = item.sale_ref;
        }
        
        return row;
      }), {
        startY: yPos + 10,
        margin: { top: yPos + 10, right: 40, bottom: 40, left: 40 },
        headStyles: { 
          fillColor: [220, 220, 220],
          textColor: [0, 0, 0],
          fontStyle: 'bold',
          lineWidth: 0.5,
          lineColor: [80, 80, 80]
        },
        bodyStyles: { 
          lineWidth: 0.5,
          lineColor: [80, 80, 80]
        },
        styles: {
          cellPadding: 4,
          fontSize: 10
        },
        tableLineWidth: 0.5,
        tableLineColor: [80, 80, 80],
        drawCell: function(cell, data) {
          // Add border to each cell
          var doc = pdf;
          var color = [80, 80, 80];
          var lineWidth = 0.5;
          
          doc.setDrawColor(color[0], color[1], color[2]);
          doc.setLineWidth(lineWidth);
          
          // Draw cell border
          doc.rect(cell.x, cell.y, cell.width, cell.height);
        }
      });
      
      // Add summary information
      const finalY = pdf.lastAutoTable.finalY || yPos + 200;
      const summaryStartY = finalY + 30;
      
      pdf.setFontSize(12);
      pdf.setFont("helvetica", "bold");
      pdf.text("Résumé:", 40, summaryStartY);
      
      pdf.setFontSize(10);
      pdf.setFont("helvetica", "normal");
      const totalAmount = payments.reduce((sum, payment) => sum + parseFloat(payment.montant), 0);
      pdf.text(`Total des paiements: ${payments.length}`, 40, summaryStartY + 20);
      pdf.text(`Montant total: ${self.formatNumber(totalAmount, 2)} ${self.currentUser.currency}`, 40, summaryStartY + 35);
      
      // Add page numbers
      const pageCount = pdf.internal.getNumberOfPages();
      for (let i = 1; i <= pageCount; i++) {
        pdf.setPage(i);
        pdf.setFontSize(10);
        pdf.text(`Page ${i}/${pageCount}`, pageWidth - 60, pdf.internal.pageSize.getHeight() - 30);
      }
      
      // Generate filename with date
      const fileDate = `${now.getDate().toString().padStart(2, '0')}-${(now.getMonth()+1).toString().padStart(2, '0')}-${now.getFullYear()}_${now.getHours().toString().padStart(2, '0')}h${now.getMinutes().toString().padStart(2, '0')}`;
      const filename = `${filenamePrefix}_${fileDate}.pdf`;
      
      pdf.save(filename);
    },

    //------------------------------- Delete Payment -------------------------\\
    Delete_Payment(id) {
      this.$swal({
        title: this.$t("Delete.Title"),
        text: this.$t("Delete.Text"),
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        cancelButtonText: this.$t("Delete.cancelButtonText"),
        confirmButtonText: this.$t("Delete.confirmButtonText")
      }).then(result => {
        if (result.value) {
          // Find the payment to determine its type
          const payment = this.payments.find(p => p.id === id);
          
          if (!payment) {
            this.$swal(
              this.$t("Delete.Failed"),
              this.$t("Delete.Notfound"),
              "warning"
            );
            return;
          }                    
        }
      });
    },

    get_data_loaded() {
      if (this.today_mode) {
        let today = new Date()
        this.startDate = today.getFullYear() + '-01-01';
        this.endDate = new Date().toJSON().slice(0, 10);
        this.dateRange.startDate = today.getFullYear() + '-01-01';
        this.dateRange.endDate = new Date().toJSON().slice(0, 10);
      }
    },

    // Handle tab change
    onTabChange(tabIndex) {
      this.activeTab = tabIndex;
      // Load data for the active tab if not already loaded
      if (tabIndex === 0 && this.globalPayments.length === 0) {
        this.getGlobalPayments();
      } else if (tabIndex === 1 && this.directPayments.length === 0) {
        this.getDirectPayments();
      }
    },

    // Get both payments (initial load and filters)
    getPayments() {
      this.getGlobalPayments();
      this.getDirectPayments();
    },

    // Get Global Payments with pagination
    getGlobalPayments() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);

      // Ensure all filter values are strings
      this.Filter_client = this.Filter_client === null ? "" : this.Filter_client;
      this.Filter_Ref = this.Filter_Ref === null ? "" : this.Filter_Ref;
      this.Filter_Reg = this.Filter_Reg === null ? "" : this.Filter_Reg;

      this.get_data_loaded();
      
      axios
        .get("report/client_payments_report", {
          params: {
            page: this.globalCurrentPage,
            SortField: this.serverParams.sort.field,
            SortType: this.serverParams.sort.type,
            search: this.search,
            limit: this.globalPerPage,
            client_id: this.Filter_client,
            Ref: this.Filter_Ref,
            Reglement: this.Filter_Reg,
            from: this.startDate ? this.startDate : "",
            to: this.endDate ? this.endDate : "",
            payment_type: 'global' // Specify payment type
          }
        })
        .then(response => {
          this.globalPayments = response.data.globalPayments || [];
          this.clients = response.data.clients || [];
          this.globalPaymentsCount = response.data.globalPaymentsCount || 0;
          this.globalPaymentsTotalAmount = response.data.globalPaymentsTotalAmount || 0;
          this.totalAmount = (response.data.globalPaymentsTotalAmount || 0) + (this.directPaymentsTotalAmount || 0);
          
          // Complete the animation of the progress bar.
          NProgress.done();
          this.isLoading = false;
          this.today_mode = false;
        })
        .catch(response => {
          // Complete the animation of the progress bar.
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
            this.today_mode = false;
          }, 500);
        });
    },

    // Get Direct Payments with pagination
    getDirectPayments() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);

      // Ensure all filter values are strings
      this.Filter_client = this.Filter_client === null ? "" : this.Filter_client;
      this.Filter_Ref = this.Filter_Ref === null ? "" : this.Filter_Ref;
      this.Filter_Reg = this.Filter_Reg === null ? "" : this.Filter_Reg;

      this.get_data_loaded();
      
      axios
        .get("report/client_payments_report", {
          params: {
            page: this.directCurrentPage,
            SortField: this.serverParams.sort.field,
            SortType: this.serverParams.sort.type,
            search: this.search,
            limit: this.directPerPage,
            client_id: this.Filter_client,
            Ref: this.Filter_Ref,
            Reglement: this.Filter_Reg,
            from: this.startDate ? this.startDate : "",
            to: this.endDate ? this.endDate : "",
            payment_type: 'direct' // Specify payment type
          }
        })
        .then(response => {
          this.directPayments = response.data.directPayments || [];
          this.clients = response.data.clients || [];
          this.directPaymentsCount = response.data.directPaymentsCount || 0;
          this.directPaymentsTotalAmount = response.data.directPaymentsTotalAmount || 0;
          this.totalAmount = (this.globalPaymentsTotalAmount || 0) + (response.data.directPaymentsTotalAmount || 0);
          
          // Complete the animation of the progress bar.
          NProgress.done();
          this.isLoading = false;
          this.today_mode = false;
        })
        .catch(response => {
          // Complete the animation of the progress bar.
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
            this.today_mode = false;
          }, 500);
        });
    },



    //---------------------- Edit Global Payment -------------------------\\
    editGlobalPayment(payment) {
      // Fill the form with current payment data
      this.editGlobalForm = {
        id: payment.id,
        Ref: payment.Ref,
        date: payment.date,
        client_id: payment.client_id,
        montant: payment.montant,
        Reglement: payment.Reglement,
        notes: payment.notes || ''
      };
      // Show the modal
      this.showEditGlobalModal = true;
    },

    //---------------------- Delete Global Payment -------------------------\\
    deleteGlobalPayment(id) {
      this.$swal({
        title: this.$t('Delete.Title'),
        text: this.$t('Delete.Text'),
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: this.$t('Delete.cancelButtonText'),
        confirmButtonText: this.$t('Delete.confirmButtonText')
      }).then((result) => {
        if (result.value) {
          axios.delete('client_payments/' + id).then(() => {
            this.$swal(
              this.$t('Delete.Deleted'),
              this.$t('Delete.PaymentDeleted'),
              'success'
            );
            this.getPayments();
          }).catch((error) => {
            console.error('Delete error:', error);
            this.$swal(
              this.$t('Delete.Failed'),
              this.$t('Delete.Therewassomethingwronge'),
              'warning'
            );
          });
        }
      });
    },



    //---------------------- Edit Direct Payment -------------------------\\
    editDirectPayment(payment) {
      // Fill the form with current payment data
      this.editDirectForm = {
        id: payment.id,
        Ref: payment.Ref,
        date: payment.date,
        client_name: payment.client_name,
        sale_ref: payment.sale_ref,
        montant: payment.montant,
        Reglement: payment.Reglement,
        notes: payment.notes || ''
      };
      // Show the modal
      this.showEditDirectModal = true;
    },

    //---------------------- Delete Direct Payment -------------------------\\
    deleteDirectPayment(id) {
      this.$swal({
        title: this.$t('Delete.Title'),
        text: this.$t('Delete.Text'),
        type: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        cancelButtonText: this.$t('Delete.cancelButtonText'),
        confirmButtonText: this.$t('Delete.confirmButtonText')
      }).then((result) => {
        if (result.value) {
          axios.delete('payment_sale/' + id).then(() => {
            this.$swal(
              this.$t('Delete.Deleted'),
              this.$t('Delete.PaymentDeleted'),
              'success'
            );
            this.getPayments();
          }).catch((error) => {
            console.error('Delete error:', error);
            this.$swal(
              this.$t('Delete.Failed'),
              this.$t('Delete.Therewassomethingwronge'),
              'warning'
            );
          });
        }
      });
    },



    //---------------------- Update Global Payment -------------------------\\
    updateGlobalPayment() {
      this.isUpdating = true;
      
      axios.put(`client_payments/${this.editGlobalForm.id}`, {
        Ref: this.editGlobalForm.Ref,
        date: this.editGlobalForm.date,
        client_id: this.editGlobalForm.client_id,
        montant: this.editGlobalForm.montant,
        Reglement: this.editGlobalForm.Reglement,
        notes: this.editGlobalForm.notes
      }).then(() => {
        this.$swal(
          this.$t('Success'),
          this.$t('PaymentUpdatedSuccessfully'),
          'success'
        );
        this.showEditGlobalModal = false;
        this.getPayments(); // Refresh the data
        this.isUpdating = false;
      }).catch((error) => {
        console.error('Update error:', error);
        this.$swal(
          this.$t('Error'),
          this.$t('SomethingWentWrong'),
          'error'
        );
        this.isUpdating = false;
      });
    },

    //---------------------- Update Direct Payment -------------------------\\
    updateDirectPayment() {
      this.isUpdating = true;
      
      axios.put(`payment_sale/${this.editDirectForm.id}`, {
        Ref: this.editDirectForm.Ref,
        date: this.editDirectForm.date,
        montant: this.editDirectForm.montant,
        Reglement: this.editDirectForm.Reglement,
        notes: this.editDirectForm.notes
      }).then(() => {
        this.$swal(
          this.$t('Success'),
          this.$t('PaymentUpdatedSuccessfully'),
          'success'
        );
        this.showEditDirectModal = false;
        this.getPayments(); // Refresh the data
        this.isUpdating = false;
      }).catch((error) => {
        console.error('Update error:', error);
        this.$swal(
          this.$t('Error'),
          this.$t('SomethingWentWrong'),
          'error'
        );
        this.isUpdating = false;
      });
    },

    //---------------------- Reset Edit Global Form -------------------------\\
    resetEditGlobalForm() {
      this.editGlobalForm = {
        id: null,
        Ref: '',
        date: '',
        client_id: null,
        montant: '',
        Reglement: '',
        notes: ''
      };
      this.isUpdating = false;
    },

    //---------------------- Reset Edit Direct Form -------------------------\\
    resetEditDirectForm() {
      this.editDirectForm = {
        id: null,
        Ref: '',
        date: '',
        client_name: '',
        sale_ref: '',
        montant: '',
        Reglement: '',
        notes: ''
      };
      this.isUpdating = false;
    },


  },

  //----------------------------- Created function-------------------\\
  created: function() {
    this.getPayments();
  }
};
</script>

<style scoped>
.btn-group .btn {
  border-radius: 0;
  margin-right: 1px;
}

.btn-group .btn:first-child {
  border-top-left-radius: 0.25rem;
  border-bottom-left-radius: 0.25rem;
}

.btn-group .btn:last-child {
  border-top-right-radius: 0.25rem;
  border-bottom-right-radius: 0.25rem;
  margin-right: 0;
}

.btn-group .btn:hover {
  z-index: 1;
}

/* Card styling improvements */
.text-center .d-flex {
  gap: 0.5rem;
}

/* Tab header improvements */
.nav-tabs .nav-link {
  color: #495057;
  font-weight: 500;
}

.nav-tabs .nav-link.active {
  color: #007bff;
  font-weight: 600;
}

/* Table action buttons hover effects */
.btn-outline-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(0,123,255,0.25);
}

.btn-outline-success:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(40,167,69,0.25);
}

.btn-outline-info:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(23,162,184,0.25);
}

.btn-outline-warning:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(255,193,7,0.25);
}

.btn-outline-danger:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 4px rgba(220,53,69,0.25);
}

/* Transition effects */
.btn {
  transition: all 0.2s ease-in-out;
}
</style>