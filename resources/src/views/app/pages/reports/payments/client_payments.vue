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

    <b-card class="wrapper" v-if="!isLoading">
      <b-row>
        <b-col md="12" class="mb-3">
          <b-button variant="outline-success" size="sm" @click="Payment_PDF">
            <i class="i-File-Copy"></i> {{ $t("PDF") }}
          </b-button>          
        </b-col>
      </b-row>

      <b-table
        show-empty
        stacked="md"
        :items="payments"
        :fields="fields"
        :per-page="perPage"
        :current-page="currentPage"
        :total-rows="totalRows"
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

        <template v-slot:cell-actions="row">
          <div class="dropdown">
            <b-dropdown size="sm" text="Actions" class="btn-normal">
              <b-dropdown-item @click="PDF_Payment(row.item)">
                <i class="i-File-TXT"></i> {{ $t('DownloadPdf') }}
              </b-dropdown-item>
              <b-dropdown-item @click="Delete_Payment(row.item.id)">
                <i class="i-Close-Window"></i> {{ $t('Delete') }}
              </b-dropdown-item>
            </b-dropdown>
          </div>
        </template>
      </b-table>

      <b-row>
        <b-col md="6" class="mt-4">
          <p>{{ $t('Showing') }} {{ firstItem }} {{ $t('to') }} {{ lastItem }} {{ $t('of') }} {{ totalRows }} {{ $t('entries') }}</p>
        </b-col>
        <b-col md="6" class="mt-4">
          <b-pagination
            v-model="currentPage"
            :total-rows="totalRows"
            :per-page="perPage"
            class="my-0 pagination-sm justify-content-end"
            @change="getPayments"
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
      clients: [],
      sales: [],
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
    firstItem() {
      const firstItem = (this.currentPage - 1) * this.perPage + 1;
      return this.totalRows ? firstItem : 0;
    },
    
    // Get last item for display
    lastItem() {
      const lastItem = this.currentPage * this.perPage;
      return this.totalRows ? (lastItem > this.totalRows ? this.totalRows : lastItem) : 0;
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
      this.getPayments();
    },

    // Submit Filter Date Range
    Submit_filter_dateRange() {
      this.startDate = this.dateRange.startDate;
      this.endDate = this.dateRange.endDate;
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

      // Sort payments by ID before generating PDF
      const sortedPayments = [...this.payments].sort((a, b) => a.id - b.id);

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
      yPos += 20;
      
      // Define table columns with better formatting
      let columns = [
        { title: "Date", dataKey: "date" },
        { title: "Référence", dataKey: "Ref" },
        { title: "Client", dataKey: "client_name" },
        { title: "Mode de paiement", dataKey: "Reglement" },
        { title: "Montant", dataKey: "amount" }
      ];
      
      // Simple table styling with borders
      pdf.autoTable(columns, sortedPayments.map(item => {
        return {
          date: item.date,
          Ref: item.Ref,
          client_name: item.client_name,
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
        this.startDate = today.getFullYear();
        this.endDate = new Date().toJSON().slice(0, 10);
        this.dateRange.startDate = today.getFullYear();
        this.dateRange.endDate = new Date().toJSON().slice(0, 10);
      }
    },

    getPayments() {
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
            page: this.serverParams.page,
            SortField: this.serverParams.sort.field,
            SortType: this.serverParams.sort.type,
            search: this.search,
            limit: this.limit,
            client_id: this.Filter_client,
            Ref: this.Filter_Ref,
            Reglement: this.Filter_Reg,
            from: this.startDate ? this.startDate : "",
            to: this.endDate ? this.endDate : ""
          }
        })
        .then(response => {
          this.payments = response.data.payments;
          this.clients = response.data.clients;
          this.sales = response.data.sales;
          this.totalRows = response.data.totalRows;
          
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
    }
  },

  //----------------------------- Created function-------------------\\
  created: function() {
    this.getPayments();
  }
};
</script>