<template>
  <div class="main-content">
    <breadcumb :page="$t('CustomersReport')" :folder="$t('Reports')" />
    <div
      v-if="isLoading"
      class="loading_page spinner spinner-primary mr-3"
    ></div>

    <b-row v-if="!isLoading">
      <!-- ICON BG -->

      <b-col lg="3" md="6" sm="12">
        <b-card
          class="card-icon-bg card-icon-bg-primary o-hidden mb-30 text-center"
        >
          <div class="content">
            <p class="text-muted mt-2 mb-0">{{ $t("Sales") }}</p>
            <p class="text-primary text-24 line-height-1 mb-2">
              {{ client.total_sales }}
            </p>
          </div>
        </b-card>
      </b-col>
      <b-col lg="3" md="6" sm="12">
        <b-card
          class="card-icon-bg card-icon-bg-primary o-hidden mb-30 text-center"
        >
          <i class="i-Financial"></i>
          <div class="content">
            <p class="text-muted mt-2 mb-0">{{ $t("TotalAmount") }}</p>
            <p class="text-primary text-24 line-height-1 mb-2">
              {{ currentUser.currency }}
              {{ formatNumber(client.total_amount, 2) }}
            </p>
          </div>
        </b-card>
      </b-col>
      <b-col lg="3" md="6" sm="12">
        <b-card
          class="card-icon-bg card-icon-bg-primary o-hidden mb-30 text-center"
        >
          <i class="i-Money-Bag"></i>
          <div class="content">
            <p class="text-muted mt-2 mb-0">{{ $t("Due") }}</p>
            <p class="text-primary text-24 line-height-1 mb-2">
              {{ currentUser.currency }} {{ formatNumber(client.total_credit, 2) }}
            </p>
          </div>
        </b-card>
      </b-col>
    </b-row>

    <b-row v-if="!isLoading">
      <b-col md="12">
        <b-card class="card mb-30" header-bg-variant="transparent ">
          <b-tabs active-nav-item-class="nav nav-tabs" content-class="mt-3">
            <!-- Sales Table -->
            <b-tab :title="$t('Sales')">
              <vue-good-table
                mode="remote"
                :columns="columns_sales"
                :totalRows="totalRows_sales"
                :rows="sales"
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
                  <b-button
                    @click="Sales_PDF()"
                    size="sm"
                    variant="outline-success ripple m-1"
                  >
                    <i class="i-File-Copy"></i> PDF
                  </b-button>
                </div>
                
                <!-- Sales Total Footer -->
                <template slot="table-footer">
                  <div class="vgt-footer-total bg-light p-3 border-top">
                    <div class="row">
                      <div class="col-md-8">
                        <strong>{{ $t('Total') }}</strong>
                      </div>
                      <div class="col-md-4 text-right">
                        <strong>{{ currentUser.currency }} {{ formatNumber(salesTotal, 2) }}</strong>
                      </div>
                    </div>
                  </div>
                </template>
                <template slot="table-row" slot-scope="props">
                  <div v-if="props.column.field == 'statut'">
                    <span
                      v-if="props.row.statut == 'completed'"
                      class="badge badge-outline-success"
                      >{{ $t("complete") }}</span
                    >
                    <span
                      v-else-if="props.row.statut == 'pending'"
                      class="badge badge-outline-info"
                      >{{ $t("Pending") }}</span
                    >
                    <span v-else class="badge badge-outline-warning">{{
                      $t("Ordered")
                    }}</span>
                  </div>
                  <div v-else-if="props.column.field == 'shipping_status'">
                    <span
                      v-if="props.row.shipping_status == 'ordered'"
                      class="badge badge-outline-warning"
                      >{{ $t("Ordered") }}</span
                    >

                    <span
                      v-else-if="props.row.shipping_status == 'packed'"
                      class="badge badge-outline-info"
                      >{{ $t("Packed") }}</span
                    >

                    <span
                      v-else-if="props.row.shipping_status == 'shipped'"
                      class="badge badge-outline-secondary"
                      >{{ $t("Shipped") }}</span
                    >

                    <span
                      v-else-if="props.row.shipping_status == 'delivered'"
                      class="badge badge-outline-success"
                      >{{ $t("Delivered") }}</span
                    >

                    <span
                      v-else-if="props.row.shipping_status == 'cancelled'"
                      class="badge badge-outline-danger"
                      >{{ $t("Cancelled") }}</span
                    >
                  </div>
                  <div v-else-if="props.column.field == 'Ref'">
                    <router-link :to="'/app/sales/detail/' + props.row.id">
                      <span class="ul-btn__text ml-1">{{ props.row.Ref }}</span>
                    </router-link>
                  </div>
                </template>
              </vue-good-table>
            </b-tab>

            <!-- Quotations Table -->
            <b-tab :title="$t('Quotations')">
              <vue-good-table
                mode="remote"
                :columns="columns_quotations"
                :totalRows="totalRows_quotations"
                :rows="quotations"
                @on-page-change="PageChangeQuotation"
                @on-per-page-change="onPerPageChangeQuotation"
                @on-search="onSearch_quotations"
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
                  <b-button
                    @click="Quotation_PDF()"
                    size="sm"
                    variant="outline-success ripple m-1"
                  >
                    <i class="i-File-Copy"></i> PDF
                  </b-button>
                </div>
                
                <!-- Quotations Total Footer -->
                <template slot="table-footer">
                  <div class="vgt-footer-total bg-light p-3 border-top">
                    <div class="row">
                      <div class="col-md-8">
                        <strong>{{ $t('Total') }}</strong>
                      </div>
                      <div class="col-md-4 text-right">
                        <strong>{{ currentUser.currency }} {{ formatNumber(quotationsTotal, 2) }}</strong>
                      </div>
                    </div>
                  </div>
                </template>
                
                <template slot="table-row" slot-scope="props">
                  <div v-if="props.column.field == 'statut'">
                    <span
                      v-if="props.row.statut == 'sent'"
                      class="badge badge-outline-success"
                      >{{ $t("Sent") }}</span
                    >
                    <span v-else class="badge badge-outline-info">{{
                      $t("Pending")
                    }}</span>
                  </div>
                  <div v-else-if="props.column.field == 'Ref'">
                    <router-link :to="'/app/quotations/detail/' + props.row.id">
                      <span class="ul-btn__text ml-1">{{ props.row.Ref }}</span>
                    </router-link>
                  </div>
                </template>
              </vue-good-table>
            </b-tab>

            <!-- Returns Table -->
            <b-tab :title="$t('Returns')">
              <vue-good-table
                mode="remote"
                :columns="columns_returns"
                :totalRows="totalRows_returns"
                :rows="returns_customer"
                @on-page-change="PageChangeReturn"
                @on-per-page-change="onPerPageChangeReturn"
                @on-search="onSearch_return_sales"
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
                  <b-button
                    @click="Sale_Return_PDF()"
                    size="sm"
                    variant="outline-success ripple m-1"
                  >
                    <i class="i-File-Copy"></i> PDF
                  </b-button>
                </div>
                
                <!-- Returns Total Footer -->
                <template slot="table-footer">
                  <div class="vgt-footer-total bg-light p-3 border-top">
                    <div class="row">
                      <div class="col-md-8">
                        <strong>{{ $t('Total') }}</strong>
                      </div>
                      <div class="col-md-4 text-right">
                        <strong>{{ currentUser.currency }} {{ formatNumber(returnsTotal, 2) }}</strong>
                      </div>
                    </div>
                  </div>
                </template>
                
                <template slot="table-row" slot-scope="props">
                  <div v-if="props.column.field == 'statut'">
                    <span
                      v-if="props.row.statut == 'received'"
                      class="badge badge-outline-success"
                      >{{ $t("Received") }}</span
                    >
                    <span v-else class="badge badge-outline-info">{{
                      $t("Pending")
                    }}</span>
                  </div>
                  <div v-else-if="props.column.field == 'Ref'">
                    <router-link
                      :to="'/app/sale_return/detail/' + props.row.id"
                    >
                      <span class="ul-btn__text ml-1">{{ props.row.Ref }}</span>
                    </router-link>
                  </div>
                  <div
                    v-else-if="
                      props.column.field == 'sale_ref' && props.row.sale_id
                    "
                  >
                    <router-link :to="'/app/sales/detail/' + props.row.sale_id">
                      <span class="ul-btn__text ml-1">{{
                        props.row.sale_ref
                      }}</span>
                    </router-link>
                  </div>
                </template>
              </vue-good-table>
            </b-tab>

            <!-- Payments Table -->
            <b-tab :title="$t('DirectPayments')">
              <vue-good-table
                mode="remote"
                :columns="columns_direct_payments"
                :totalRows="totalRows_direct_payments"
                :rows="direct_payments"
                @on-page-change="PageChangeDirectPayments"
                @on-per-page-change="onPerPageChangeDirectPayments"
                @on-search="onSearch_direct_payments"
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
                <template slot="table-row" slot-scope="props">
                  <div v-if="props.column.field == 'actions'">
                    <a
                      @click="showOldPaymentDetails(props.row.id)"
                      class="cursor-pointer"
                    >
                      <i class="i-Eye text-25 text-info"></i>
                    </a>
                  </div>
                  <div
                    v-else-if="
                      props.column.field == 'sale_ref' && props.row.sale_id
                    "
                  >
                    <router-link :to="'/app/sales/detail/' + props.row.sale_id">
                      <span class="ul-btn__text ml-1">{{
                        props.row.sale_ref
                      }}</span>
                    </router-link>
                  </div>
                </template>
                
                <!-- Direct Payments Total Footer -->
                <template slot="table-footer">
                  <div class="vgt-footer-total bg-light p-3 border-top">
                    <div class="row">
                      <div class="col-md-8">
                        <strong>{{ $t('Total') }}</strong>
                      </div>
                      <div class="col-md-4 text-right">
                        <strong>{{ currentUser.currency }} {{ formatNumber(directPaymentsTotal, 2) }}</strong>
                      </div>
                    </div>
                  </div>
                </template>

                <div slot="table-actions" class="mt-2 mb-3">
                  <b-button
                    @click="DirectPayments_PDF()"
                    size="sm"
                    variant="outline-success ripple m-1"
                  >
                    <i class="i-File-Copy"></i> PDF
                  </b-button>
                </div>
              </vue-good-table>
            </b-tab>

            <!-- Global Payments Table -->
            <b-tab :title="$t('GlobalPayments')">
              <vue-good-table
                mode="remote"
                :columns="columns_global_payments"
                :totalRows="totalRows_global_payments"
                :rows="global_payments"
                @on-page-change="PageChangeGlobalPayments"
                @on-per-page-change="onPerPageChangeGlobalPayments"
                @on-search="onSearch_global_payments"
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
                <!-- Global Payments Total Footer -->
                <template slot="table-footer">
                  <div class="vgt-footer-total bg-light p-3 border-top">
                    <div class="row">
                      <div class="col-md-8">
                        <strong>{{ $t('Total') }}</strong>
                      </div>
                      <div class="col-md-4 text-right">
                        <strong>{{ currentUser.currency }} {{ formatNumber(globalPaymentsTotal, 2) }}</strong>
                      </div>
                    </div>
                  </div>
                </template>
                
                <div slot="table-actions" class="mt-2 mb-3">
                  <b-button
                    @click="GlobalPayments_PDF()"
                    size="sm"
                    variant="outline-success ripple m-1"
                  >
                    <i class="i-File-Copy"></i> PDF
                  </b-button>
                </div>
              </vue-good-table>
            </b-tab>
          </b-tabs>
        </b-card>
      </b-col>
    </b-row>
  </div>
</template>

<script>
import { mapActions, mapGetters } from "vuex";
import jsPDF from "jspdf";
import "jspdf-autotable";

export default {
  data() {
    return {
      totalRows_quotations: "",
      totalRows_sales: "",
      totalRows_returns: "",
      totalRows_payments: "",
      totalRows_direct_payments: "",
      totalRows_global_payments: "",
      limit_quotations: "10",
      limit_returns: "10",
      limit_sales: "10",
      limit_payments: "10",
      sales_page: 1,
      quotations_page: 1,
      Return_sale_page: 1,
      Payment_sale_page: 1,
      isLoading: true,
      payments: [],
      sales: [],
      quotations: [],
      returns_customer: [],
      direct_payments: [],
      global_payments: [],

      search_sales: "",
      search_payments: "",
      search_quotations: "",
      search_return_sales: "",
      search_direct_payments: "",
      search_global_payments: "",

      client: {
        id: "",
        name: "",
        total_sales: 0,
        total_amount: 0,
        total_paid: 0,
        due: 0,
      },
      showPaymentDetailsModal: false,
      isLoadingDetails: true,
      paymentDetails: {},
      paymentAllocations: [],
      totalAllocated: 0,
      unallocated: 0,
      showSalePaymentsModal: false,
      isLoadingSalePayments: true,
      salePayments: [],
      columns_direct_payments: [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Sale"),
          field: "sale_ref",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("ModePaiement"),
          field: "Reglement",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Amount"),
          field: "montant",
          tdClass: "text-left",
          thClass: "text-left",
          type: "decimal",
          sortable: false,
        },
      ],
      columns_global_payments: [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("ModePaiement"),
          field: "Reglement",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Amount"),
          field: "montant",
          tdClass: "text-left",
          thClass: "text-left",
          type: "decimal",
          sortable: false,
        },
      ],
    };
  },

  computed: {
    ...mapGetters(["currentUser"]),
    
    // Calculate total for Sales table
    salesTotal() {
      return this.sales.reduce((total, sale) => {
        return total + parseFloat(sale.GrandTotal || 0);
      }, 0);
    },
    
    // Calculate total for Quotations table
    quotationsTotal() {
      return this.quotations.reduce((total, quotation) => {
        return total + parseFloat(quotation.GrandTotal || 0);
      }, 0);
    },
    
    // Calculate total for Returns table
    returnsTotal() {
      return this.returns_customer.reduce((total, returnItem) => {
        return total + parseFloat(returnItem.GrandTotal || 0);
      }, 0);
    },
    
    // Calculate total for Direct Payments table
    directPaymentsTotal() {
      return this.direct_payments.reduce((total, payment) => {
        return total + parseFloat(payment.montant || 0);
      }, 0);
    },
    
    // Calculate total for Global Payments table
    globalPaymentsTotal() {
      return this.global_payments.reduce((total, payment) => {
        return total + parseFloat(payment.montant || 0);
      }, 0);
    },
    columns_quotations() {
      return [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Total"),
          field: "GrandTotal",
          tdClass: "text-left",
          thClass: "text-left",
        },
        {
          label: this.$t("PaymentType"),
          field: "payment_type",
          tdClass: "text-left",
          thClass: "text-left",
          html: true,
          sortable: false,
        },
        {
          label: this.$t("Status"),
          field: "statut",
          tdClass: "text-left",
          thClass: "text-left",
          html: true,
          sortable: false,
        },
      ];
    },
    columns_sales() {
      return [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },

        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left",
        },
        {
          label: this.$t("Total"),
          field: "GrandTotal",
          type: "decimal",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
      ];
    },
    columns_returns() {
      return [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Sale_Ref"),
          field: "sale_ref",
          tdClass: "text-left",
          thClass: "text-left",
        },
        {
          label: this.$t("warehouse"),
          field: "warehouse_name",
          tdClass: "text-left",
          thClass: "text-left",
        },

        {
          label: this.$t("Total"),
          field: "GrandTotal",
          type: "decimal",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Status"),
          field: "statut",
          html: true,
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
      ];
    },
    columns_payments() {
      return [
        {
          label: this.$t("date"),
          field: "date",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Reference"),
          field: "Ref",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Sale"),
          field: "sale_ref",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Customer"),
          field: "client_name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("ModePaiement"),
          field: "Reglement",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
        {
          label: this.$t("Amount"),
          field: "montant",
          tdClass: "text-left",
          thClass: "text-left",
          type: "decimal",
          sortable: false,
        },
        {
          label: this.$t("PaymentType"),
          field: "payment_type",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false,
        },
      ];
    },
  },

  methods: {
    //----------------------------------- Sales PDF ------------------------------\\
    Sales_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      // Set document properties
      pdf.setProperties({
        title: "Liste des ventes client",
      });

      // Add styled title
      let pageWidth = pdf.internal.pageSize.getWidth();
      let title = "Liste des ventes client";

      // Title styling
      pdf.setFillColor(240, 240, 240);
      pdf.rect(0, 0, pageWidth, 60, "F");
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

      // Client and date information with proper spacing
      const now = new Date();
      const today = `${now.getDate().toString().padStart(2, "0")}/${(
        now.getMonth() + 1
      )
        .toString()
        .padStart(2, "0")}/${now.getFullYear()} ${now
        .getHours()
        .toString()
        .padStart(2, "0")}:${now.getMinutes().toString().padStart(2, "0")}`;

      let yPos = 80;
      if (self.client && self.client.client_name) {
        pdf.text(`Client: ${self.client.client_name}`, 40, yPos);
        yPos += 20;

        if (self.client.client_address) {
          pdf.text(`Adresse: ${self.client.client_address}`, 40, yPos);
          yPos += 20;
        }
      } else if (self.client && self.client.name) {
        pdf.text(`Client: ${self.client.name}`, 40, yPos);
        yPos += 20;
      }

      pdf.text(`Date: ${today}`, 40, yPos);
      yPos += 20;

      // Define table columns - using the existing columns
      let columns = [
        { title: "Référence", dataKey: "Ref" },
        { title: "Client", dataKey: "client_name" },
        { title: "Entrepôt", dataKey: "warehouse_name" },
        { title: "Statut", dataKey: "statut" },
        { title: "Total", dataKey: "GrandTotal" },
      ];

      // Simple table styling with borders
      let finalY = pdf.autoTable(columns, self.sales, {
        startY: yPos + 10,
        margin: { top: yPos + 10, right: 40, bottom: 40, left: 40 },
        headStyles: {
          fillColor: [220, 220, 220],
          textColor: [0, 0, 0],
          fontStyle: "bold",
          lineWidth: 0.5,
          lineColor: [80, 80, 80],
        },
        bodyStyles: {
          lineWidth: 0.5,
          lineColor: [80, 80, 80],
        },
        styles: {
          cellPadding: 4,
          fontSize: 10,
        },
        tableLineWidth: 0.5,
        tableLineColor: [80, 80, 80],
        drawCell: function (cell, data) {
          // Add border to each cell
          var doc = pdf;
          var color = [80, 80, 80];
          var lineWidth = 0.5;

          doc.setDrawColor(color[0], color[1], color[2]);
          doc.setLineWidth(lineWidth);

          // Draw cell border
          doc.rect(cell.x, cell.y, cell.width, cell.height);
        },
      });

      // Add total at the bottom
      pdf.setFont("helvetica", "bold");
      pdf.setFontSize(12);
      let total = self.salesTotal;
      pdf.text(`Total: ${self.formatNumber(total, 2)} ${self.currentUser.currency}`, 40, finalY + 30);

      // Add page numbers - simple format
      const pageCount = pdf.internal.getNumberOfPages();
      for (let i = 1; i <= pageCount; i++) {
        pdf.setPage(i);
        pdf.setFontSize(10);
        pdf.text(
          `Page ${i}/${pageCount}`,
          pageWidth - 60,
          pdf.internal.pageSize.getHeight() - 30
        );
      }

      // Generate filename with date
      const fileDate = `${now.getDate().toString().padStart(2, "0")}-${(
        now.getMonth() + 1
      )
        .toString()
        .padStart(2, "0")}-${now.getFullYear()}_${now
        .getHours()
        .toString()
        .padStart(2, "0")}h${now.getMinutes().toString().padStart(2, "0")}`;
      const clientName =
        self.client && (self.client.client_name || self.client.name)
          ? `_${(self.client.client_name || self.client.name).replace(
              /\s+/g,
              "_"
            )}`
          : "";
      const filename = `Liste_ventes${clientName}_${fileDate}.pdf`;

      pdf.save(filename);
    },

    //------------------------------------- Quotations PDF -------------------------\\
    Quotation_PDF() {
      var self = this;

      let pdf = new jsPDF("p", "pt");
      let columns = [
        { title: "Date", dataKey: "date" },
        { title: "Ref", dataKey: "Ref" },
        { title: "Client", dataKey: "client_name" },
        { title: "Warehouse", dataKey: "warehouse_name" },
        { title: "Status", dataKey: "statut" },
        { title: "Total", dataKey: "GrandTotal" },
      ];
      
      let finalY = pdf.autoTable(columns, self.quotations, {
        startY: 50,
      });
      
      pdf.text("Quotation List", 40, 25);
      
      // Add total at the bottom
      pdf.setFont("helvetica", "bold");
      pdf.setFontSize(12);
      let total = self.quotationsTotal;
      pdf.text(`Total: ${self.formatNumber(total, 2)} ${self.currentUser.currency}`, 40, finalY + 30);
      
      pdf.save("Quotation_List.pdf");
    },

    //----------------------------------------- Sales Return PDF -----------------------\\
    Sale_Return_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      // Set document properties
      pdf.setProperties({
        title: "Liste des retours client",
      });

      // Add styled title
      let pageWidth = pdf.internal.pageSize.getWidth();
      let title = "Liste des retours client";

      // Title styling
      pdf.setFillColor(240, 240, 240);
      pdf.rect(0, 0, pageWidth, 60, "F");
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

      // Client and date information with proper spacing
      const now = new Date();
      const today = `${now.getDate().toString().padStart(2, "0")}/${(
        now.getMonth() + 1
      )
        .toString()
        .padStart(2, "0")}/${now.getFullYear()} ${now
        .getHours()
        .toString()
        .padStart(2, "0")}:${now.getMinutes().toString().padStart(2, "0")}`;

      let yPos = 80;
      if (self.client && self.client.client_name) {
        pdf.text(`Client: ${self.client.client_name}`, 40, yPos);
        yPos += 20;

        if (self.client.client_address) {
          pdf.text(`Adresse: ${self.client.client_address}`, 40, yPos);
          yPos += 20;
        }
      } else if (self.client && self.client.name) {
        pdf.text(`Client: ${self.client.name}`, 40, yPos);
        yPos += 20;
      }

      pdf.text(`Date: ${today}`, 40, yPos);
      yPos += 20;

      // Define table columns - using the existing columns
      let columns = [
        { title: "Référence", dataKey: "Ref" },
        { title: "Client", dataKey: "client_name" },
        { title: "Référence vente", dataKey: "sale_ref" },
        { title: "Entrepôt", dataKey: "warehouse_name" },
        { title: "Total", dataKey: "GrandTotal" },
        { title: "Statut", dataKey: "statut" },
      ];

      // Simple table styling with borders
      let finalY = pdf.autoTable(columns, self.returns_customer, {
        startY: yPos + 10,
        margin: { top: yPos + 10, right: 40, bottom: 40, left: 40 },
        headStyles: {
          fillColor: [220, 220, 220],
          textColor: [0, 0, 0],
          fontStyle: "bold",
          lineWidth: 0.5,
          lineColor: [80, 80, 80],
        },
        bodyStyles: {
          lineWidth: 0.5,
          lineColor: [80, 80, 80],
        },
        styles: {
          cellPadding: 4,
          fontSize: 10,
        },
        tableLineWidth: 0.5,
        tableLineColor: [80, 80, 80],
        drawCell: function (cell, data) {
          // Add border to each cell
          var doc = pdf;
          var color = [80, 80, 80];
          var lineWidth = 0.5;

          doc.setDrawColor(color[0], color[1], color[2]);
          doc.setLineWidth(lineWidth);

          // Draw cell border
          doc.rect(cell.x, cell.y, cell.width, cell.height);
        },
      });

      // Add total at the bottom
      pdf.setFont("helvetica", "bold");
      pdf.setFontSize(12);
      let total = self.returnsTotal;
      pdf.text(`Total: ${self.formatNumber(total, 2)} ${self.currentUser.currency}`, 40, finalY + 30);

      // Add page numbers - simple format
      const pageCount = pdf.internal.getNumberOfPages();
      for (let i = 1; i <= pageCount; i++) {
        pdf.setPage(i);
        pdf.setFontSize(10);
        pdf.text(
          `Page ${i}/${pageCount}`,
          pageWidth - 60,
          pdf.internal.pageSize.getHeight() - 30
        );
      }

      // Generate filename with date
      const fileDate = `${now.getDate().toString().padStart(2, "0")}-${(
        now.getMonth() + 1
      )
        .toString()
        .padStart(2, "0")}-${now.getFullYear()}_${now
        .getHours()
        .toString()
        .padStart(2, "0")}h${now.getMinutes().toString().padStart(2, "0")}`;
      const clientName =
        self.client && (self.client.client_name || self.client.name)
          ? `_${(self.client.client_name || self.client.name).replace(
              /\s+/g,
              "_"
            )}`
          : "";
      const filename = `Liste_retours${clientName}_${fileDate}.pdf`;

      pdf.save(filename);
    },

    //----------------------------------- Sales PDF ------------------------------\\
    Payments_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");
      let columns = [
        { title: "Date", dataKey: "date" },
        { title: "Ref", dataKey: "Ref" },
        { title: "Sale", dataKey: "Sale_Ref" },
        { title: "Reglement", dataKey: "Reglement" },
        { title: "Amount", dataKey: "montant" },
      ];
      pdf.autoTable(columns, self.payments);
      pdf.text("Payments List", 40, 25);
      pdf.save("Payments_List.pdf");
    },

    //------------------------------Formetted Numbers -------------------------\\
    formatNumber(number, dec) {
      const value = (
        typeof number === "string" ? number : number.toString()
      ).split(".");
      if (dec <= 0) return value[0];
      let formated = value[1] || "";
      if (formated.length > dec)
        return `${value[0]}.${formated.substr(0, dec)}`;
      while (formated.length < dec) formated += "0";
      return `${value[0]}.${formated}`;
    },

    //------------------------------ Show Reports -------------------------\\
    Get_Reports() {
      let id = this.$route.params.id;
      axios
        .get(`report/client/${id}`)
        .then((response) => {
          this.client = response.data.report;
        })
        .catch((response) => {});
    },

    //--------------------------- Event Page Change -------------\\
    PageChangeSales({ currentPage }) {
      if (this.sales_page !== currentPage) {
        this.Get_Sales(currentPage);
      }
    },

    //--------------------------- Limit Page Sales -------------\\
    onPerPageChangeSales({ currentPerPage }) {
      if (this.limit_sales !== currentPerPage) {
        this.limit_sales = currentPerPage;
        this.Get_Sales(1);
      }
    },

    onSearch_sales(value) {
      this.search_sales = value.searchTerm;
      this.Get_Sales(1);
    },

    //--------------------------- Get sales By Customer -------------\\
    Get_Sales(page) {
      axios
        .get(
          "/report/client_sales?page=" +
            page +
            "&limit=" +
            this.limit_sales +
            "&search=" +
            this.search_sales +
            "&id=" +
            this.$route.params.id
        )
        .then((response) => {
          this.sales = response.data.sales;
          this.totalRows_sales = response.data.totalRows;
        })
        .catch((response) => {});
    },

    //--------------------------- Event Page Change -------------\\
    PageChangePayments({ currentPage }) {
      if (this.Payment_sale_page !== currentPage) {
        this.Get_Payments(currentPage);
      }
    },

    //--------------------------- Limit Page Payments -------------\\
    onPerPageChangePayments({ currentPerPage }) {
      if (this.limit_payments !== currentPerPage) {
        this.limit_payments = currentPerPage;
        this.Get_Payments(1);
      }
    },

    onSearch_payments(value) {
      this.search_payments = value.searchTerm;
      this.Get_Payments(1);
    },

    //--------------------------- Get Payments By Customer -------------\\
    Get_Payments(page) {
      axios
        .get(
          "report/client_payments?page=" +
            page +
            "&limit=" +
            this.limit_payments +
            "&search=" +
            this.search_payments +
            "&id=" +
            this.$route.params.id
        )
        .then((response) => {
          this.payments = response.data.payments;
          this.totalRows_payments = response.data.totalRows;
        })
        .catch((response) => {});
    },

    //--------------------------- Event Page Change -------------\\
    PageChangeQuotation({ currentPage }) {
      if (this.quotations_page !== currentPage) {
        this.Get_Quotations(currentPage);
      }
    },

    //--------------------------- Limit Page Quotations -------------\\
    onPerPageChangeQuotation({ currentPerPage }) {
      if (this.limit_quotations !== currentPerPage) {
        this.limit_quotations = currentPerPage;
        this.Get_Quotations(1);
      }
    },

    onSearch_quotations(value) {
      this.search_quotations = value.searchTerm;
      this.Get_Quotations(1);
    },

    //--------------------------- Get Quotations By Customer -------------\\
    Get_Quotations(page) {
      axios
        .get(
          "report/client_quotations?page=" +
            page +
            "&limit=" +
            this.limit_quotations +
            "&search=" +
            this.search_quotations +
            "&id=" +
            this.$route.params.id
        )
        .then((response) => {
          this.quotations = response.data.quotations;
          this.totalRows_quotations = response.data.totalRows;
          this.isLoading = false;
        })
        .catch((response) => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    //--------------------------- Event Page Change -------------\\
    PageChangeReturn({ currentPage }) {
      if (this.Return_sale_page !== currentPage) {
        this.Get_Returns(currentPage);
      }
    },

    //--------------------------- Limit Page Returns -------------\\
    onPerPageChangeReturn({ currentPerPage }) {
      if (this.limit_returns !== currentPerPage) {
        this.limit_returns = currentPerPage;
        this.Get_Returns(1);
      }
    },

    onSearch_return_sales(value) {
      this.search_return_sales = value.searchTerm;
      this.Get_Returns(1);
    },

    //--------------------------- Get Returns By Customer -------------\\
    Get_Returns(page) {
      axios
        .get(
          "/report/client_returns?page=" +
            page +
            "&limit=" +
            this.limit_returns +
            "&search=" +
            this.search_return_sales +
            "&id=" +
            this.$route.params.id
        )
        .then((response) => {
          this.returns_customer = response.data.returns_customer;
          this.totalRows_returns = response.data.totalRows;
        })
        .catch((response) => {});
    },

    showPaymentDetails(id) {
      this.showPaymentDetailsModal = true;
      this.isLoadingDetails = true;
      axios
        .get(`report/client_payments/${id}`)
        .then((response) => {
          this.paymentDetails = response.data.payment;
          this.paymentAllocations = response.data.allocations;
          this.totalAllocated = response.data.totalAllocated;
          this.unallocated = response.data.unallocated;
          this.isLoadingDetails = false;
        })
        .catch((response) => {
          console.error("Error loading payment details:", response);
          this.isLoadingDetails = false;
        });
    },

    showOldPaymentDetails(id) {
      this.showPaymentDetailsModal = true;
      this.isLoadingDetails = true;
      axios
        .get(`report/old_payment_detail/${id}`)
        .then((response) => {
          this.paymentDetails = response.data.payment;
          this.paymentAllocations = response.data.allocations;
          this.totalAllocated = response.data.totalAllocated;
          this.unallocated = response.data.unallocated;
          this.isLoadingDetails = false;
        })
        .catch((response) => {
          console.error("Error loading old payment details:", response);
          this.isLoadingDetails = false;
        });
    },

    showSalePaymentsDetails(id) {
      this.showSalePaymentsModal = true;
      this.isLoadingSalePayments = true;
      axios
        .get(`report/client_payments/${id}/sale_payments`)
        .then((response) => {
          this.salePayments = response.data.sale_payments;
          this.isLoadingSalePayments = false;
        })
        .catch((response) => {
          console.error("Error loading sale payments:", response);
          this.isLoadingSalePayments = false;
        });
    },

    PageChangeDirectPayments({ currentPage }) {
      if (this.Payment_sale_page !== currentPage) {
        this.Get_DirectPayments(currentPage);
      }
    },

    onPerPageChangeDirectPayments({ currentPerPage }) {
      if (this.limit_payments !== currentPerPage) {
        this.limit_payments = currentPerPage;
        this.Get_DirectPayments(1);
      }
    },

    onSearch_direct_payments(value) {
      this.search_direct_payments = value.searchTerm;
      this.Get_DirectPayments(1);
    },

    Get_DirectPayments(page) {
      axios
        .get(
          "report/direct_payments?page=" +
            page +
            "&limit=" +
            this.limit_payments +
            "&search=" +
            this.search_direct_payments +
            "&id=" +
            this.$route.params.id
        )
        .then((response) => {
          this.direct_payments = response.data.payments;
          this.totalRows_direct_payments = response.data.totalRows;
        })
        .catch((response) => {});
    },

    PageChangeGlobalPayments({ currentPage }) {
      if (this.Payment_sale_page !== currentPage) {
        this.Get_GlobalPayments(currentPage);
      }
    },

    onPerPageChangeGlobalPayments({ currentPerPage }) {
      if (this.limit_payments !== currentPerPage) {
        this.limit_payments = currentPerPage;
        this.Get_GlobalPayments(1);
      }
    },

    onSearch_global_payments(value) {
      this.search_global_payments = value.searchTerm;
      this.Get_GlobalPayments(1);
    },

    Get_GlobalPayments(page) {
      axios
        .get(
          "report/global_payments?page=" +
            page +
            "&limit=" +
            this.limit_payments +
            "&search=" +
            this.search_global_payments +
            "&id=" +
            this.$route.params.id
        )
        .then((response) => {
          this.global_payments = response.data.payments;
          this.totalRows_global_payments = response.data.totalRows;
        })
        .catch((response) => {});
    },

    DirectPayments_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      // Set document properties
      pdf.setProperties({
        title: "Relevé des paiements client",
      });

      // Add styled title
      let pageWidth = pdf.internal.pageSize.getWidth();
      let title = "Relevé des paiements client";

      // Title styling
      pdf.setFillColor(240, 240, 240);
      pdf.rect(0, 0, pageWidth, 60, "F");
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

      // Client and date information with proper spacing
      const now = new Date();
      const today = `${now.getDate().toString().padStart(2, "0")}/${(
        now.getMonth() + 1
      )
        .toString()
        .padStart(2, "0")}/${now.getFullYear()} ${now
        .getHours()
        .toString()
        .padStart(2, "0")}:${now.getMinutes().toString().padStart(2, "0")}`;

      let yPos = 80;
      if (self.client && self.client.client_name) {
        pdf.text(`Client: ${self.client.client_name}`, 40, yPos);
        yPos += 20;

        if (self.client.client_address) {
          pdf.text(`Adresse: ${self.client.client_address}`, 40, yPos);
          yPos += 20;
        }
      } else if (self.client && self.client.name) {
        pdf.text(`Client: ${self.client.name}`, 40, yPos);
        yPos += 20;
      }

      pdf.text(`Date: ${today}`, 40, yPos);
      yPos += 20;

      // Define table columns
      let columns = [
        { title: "Date", dataKey: "date" },
        { title: "Numéro de paiement", dataKey: "Ref" },
        { title: "Facture payée", dataKey: "sale_ref" },
        { title: "Montant", dataKey: "montant" },
        { title: "Mode de paiement", dataKey: "Reglement" },
      ];

      // Simple table styling with borders
      let finalY = pdf.autoTable(columns, self.direct_payments, {
        startY: yPos + 10,
        margin: { top: yPos + 10, right: 40, bottom: 40, left: 40 },
        headStyles: {
          fillColor: [220, 220, 220],
          textColor: [0, 0, 0],
          fontStyle: "bold",
          lineWidth: 0.5,
          lineColor: [80, 80, 80],
        },
        bodyStyles: {
          lineWidth: 0.5,
          lineColor: [80, 80, 80],
        },
        styles: {
          cellPadding: 4,
          fontSize: 10,
        },
        tableLineWidth: 0.5,
        tableLineColor: [80, 80, 80],
        drawCell: function (cell, data) {
          // Add border to each cell
          var doc = pdf;
          var color = [80, 80, 80];
          var lineWidth = 0.5;

          doc.setDrawColor(color[0], color[1], color[2]);
          doc.setLineWidth(lineWidth);

          // Draw cell border
          doc.rect(cell.x, cell.y, cell.width, cell.height);
        },
      });

      // Add total at the bottom
      pdf.setFont("helvetica", "bold");
      pdf.setFontSize(12);
      let total = self.directPaymentsTotal;
      pdf.text(`Total: ${self.formatNumber(total, 2)} ${self.currentUser.currency}`, 40, finalY + 30);

      // Add page numbers - simple format
      const pageCount = pdf.internal.getNumberOfPages();
      for (let i = 1; i <= pageCount; i++) {
        pdf.setPage(i);
        pdf.setFontSize(10);
        pdf.text(
          `Page ${i}/${pageCount}`,
          pageWidth - 60,
          pdf.internal.pageSize.getHeight() - 30
        );
      }

      // Generate filename with date
      const fileDate = `${now.getDate().toString().padStart(2, "0")}-${(
        now.getMonth() + 1
      )
        .toString()
        .padStart(2, "0")}-${now.getFullYear()}_${now
        .getHours()
        .toString()
        .padStart(2, "0")}h${now.getMinutes().toString().padStart(2, "0")}`;
      const clientName =
        self.client && (self.client.client_name || self.client.name)
          ? `_${(self.client.client_name || self.client.name).replace(
              /\s+/g,
              "_"
            )}`
          : "";
      const filename = `Releve_paiements_directs${clientName}_${fileDate}.pdf`;

      pdf.save(filename);
    },

    GlobalPayments_PDF() {
      var self = this;
      let pdf = new jsPDF("p", "pt");

      // Set document properties
      pdf.setProperties({
        title: "Relevé des paiements client",
      });

      // Add styled title
      let pageWidth = pdf.internal.pageSize.getWidth();
      let title = "Relevé des paiements client";

      // Title styling
      pdf.setFillColor(240, 240, 240);
      pdf.rect(0, 0, pageWidth, 60, "F");
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

      // Client and date information with proper spacing
      const now = new Date();
      const today = `${now.getDate().toString().padStart(2, "0")}/${(
        now.getMonth() + 1
      )
        .toString()
        .padStart(2, "0")}/${now.getFullYear()} ${now
        .getHours()
        .toString()
        .padStart(2, "0")}:${now.getMinutes().toString().padStart(2, "0")}`;

      let yPos = 80;
      if (self.client && self.client.client_name) {
        pdf.text(`Client: ${self.client.client_name}`, 40, yPos);
        yPos += 20;

        if (self.client.client_address) {
          pdf.text(`Adresse: ${self.client.client_address}`, 40, yPos);
          yPos += 20;
        }
      } else if (self.client && self.client.name) {
        pdf.text(`Client: ${self.client.name}`, 40, yPos);
        yPos += 20;
      }

      pdf.text(`Date: ${today}`, 40, yPos);
      yPos += 20;

      // Define table columns
      let columns = [
        { title: "Date", dataKey: "date" },
        { title: "Numéro Paiement", dataKey: "Ref" },
        { title: "Montant", dataKey: "montant" },
        { title: "Mode de paiement", dataKey: "Reglement" },
      ];

      // Simple table styling with borders
      let finalY = pdf.autoTable(columns, self.global_payments, {
        startY: yPos + 10,
        margin: { top: yPos + 10, right: 40, bottom: 40, left: 40 },
        headStyles: {
          fillColor: [220, 220, 220],
          textColor: [0, 0, 0],
          fontStyle: "bold",
          lineWidth: 0.5,
          lineColor: [80, 80, 80],
        },
        bodyStyles: {
          lineWidth: 0.5,
          lineColor: [80, 80, 80],
        },
        styles: {
          cellPadding: 4,
          fontSize: 10,
        },
        tableLineWidth: 0.5,
        tableLineColor: [80, 80, 80],
        drawCell: function (cell, data) {
          // Add border to each cell
          var doc = pdf;
          var color = [80, 80, 80];
          var lineWidth = 0.5;

          doc.setDrawColor(color[0], color[1], color[2]);
          doc.setLineWidth(lineWidth);

          // Draw cell border
          doc.rect(cell.x, cell.y, cell.width, cell.height);
        },
      });

      // Add total at the bottom
      pdf.setFont("helvetica", "bold");
      pdf.setFontSize(12);
      let total = self.globalPaymentsTotal;
      pdf.text(`Total: ${self.formatNumber(total, 2)} ${self.currentUser.currency}`, 40, finalY + 30);

      // Add page numbers - simple format
      const pageCount = pdf.internal.getNumberOfPages();
      for (let i = 1; i <= pageCount; i++) {
        pdf.setPage(i);
        pdf.setFontSize(10);
        pdf.text(
          `Page ${i}/${pageCount}`,
          pageWidth - 60,
          pdf.internal.pageSize.getHeight() - 30
        );
      }

      // Generate filename with date
      const fileDate = `${now.getDate().toString().padStart(2, "0")}-${(
        now.getMonth() + 1
      )
        .toString()
        .padStart(2, "0")}-${now.getFullYear()}_${now
        .getHours()
        .toString()
        .padStart(2, "0")}h${now.getMinutes().toString().padStart(2, "0")}`;
      const clientName =
        self.client && (self.client.client_name || self.client.name)
          ? `_${(self.client.client_name || self.client.name).replace(
              /\s+/g,
              "_"
            )}`
          : "";
      const filename = `Releve_paiements_globaux${clientName}_${fileDate}.pdf`;

      pdf.save(filename);
    },
  }, //end Methods

  //----------------------------- Created function------------------- \\

  created() {
    this.Get_Reports();
    this.Get_Sales(1);
    this.Get_Quotations(1);
    this.Get_Returns(1);
    this.Get_DirectPayments(1);
    this.Get_GlobalPayments(1);
  },

  mounted() {
    this.Get_DirectPayments(1);
    this.Get_GlobalPayments(1);
  },
};
</script>
