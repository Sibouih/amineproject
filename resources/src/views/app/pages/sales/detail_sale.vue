<template>
  <div class="main-content sale-detail">
    <breadcumb :page="$t('SaleDetail')" :folder="$t('Sales')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-if="!isLoading">
      <!-- Header -->
      <div class="sd-header">
        <div class="sd-header-main">
          <div class="sd-ref">
            <span class="sd-ref-label">{{$t('Invoice_num')}}</span>
            <h3 class="sd-ref-value">{{sale.Ref}}</h3>
          </div>
          <div class="sd-chips">
            <span class="sd-chip" :class="statusClass(sale.statut)">{{ statusLabel(sale.statut) }}</span>
            <span class="sd-chip" :class="paymentClass(sale.payment_status)">{{ paymentLabel(sale.payment_status) }}</span>
          </div>
          <div class="sd-meta">
            <span><i class="i-Calendar-4"></i> {{sale.date}}</span>
            <span><i class="i-Building"></i> {{sale.warehouse}}</span>
          </div>
        </div>

        <div class="sd-actions">
          <button
            v-if="currentUserPermissions && currentUserPermissions.includes('Sales_profit')"
            @click="Show_Profit()"
            class="btn btn-sm sd-btn sd-btn-profit"
            :disabled="profitLoading"
          >
            <i class="i-Money-2"></i> {{$t('Show_Profit')}}
          </button>
          <router-link
            v-if="currentUserPermissions && currentUserPermissions.includes('Sales_edit') && sale.sale_has_return == 'no'"
            class="btn btn-sm sd-btn sd-btn-edit"
            :to="{ name:'edit_sale', params: { id: $route.params.id } }"
          >
            <i class="i-Edit"></i> {{$t('EditSale')}}
          </router-link>
          <button @click="print()" class="btn btn-sm sd-btn sd-btn-print">
            <i class="i-Billing"></i> {{$t('print')}}
          </button>
          <button @click="Sale_SMS()" class="btn btn-sm sd-btn sd-btn-light">
            <i class="i-Speach-Bubble"></i> SMS
          </button>
          <button
            v-if="currentUserPermissions && currentUserPermissions.includes('Sales_delete') && sale.sale_has_return == 'no'"
            @click="Delete_Sale()"
            class="btn btn-sm sd-btn sd-btn-delete"
          >
            <i class="i-Close-Window"></i> {{$t('Del')}}
          </button>
        </div>
      </div>

      <!-- Summary cards -->
      <div class="sd-cards">
        <div class="sd-card">
          <div class="sd-card-label">{{$t('Total')}}</div>
          <div class="sd-card-value">{{ money(sale.GrandTotal) }}</div>
        </div>
        <div class="sd-card">
          <div class="sd-card-label">{{$t('Amount_paid')}}</div>
          <div class="sd-card-value text-success">{{ money(sale.paid_amount) }}</div>
        </div>
        <div class="sd-card">
          <div class="sd-card-label">{{$t('Due')}}</div>
          <div class="sd-card-value" :class="{'text-danger': toNum(sale.due) > 0}">{{ money(sale.due) }}</div>
        </div>
        <div class="sd-card">
          <div class="sd-card-label">{{$t('Reste_credit')}}</div>
          <div class="sd-card-value" :class="{'text-danger': toNum(sale.total_credit) > 0}">{{ money(sale.total_credit) }}</div>
        </div>
      </div>

      <b-row>
        <!-- Client -->
        <b-col lg="4" md="12" class="mb-4">
          <div class="sd-panel h-100">
            <div class="sd-panel-title"><i class="i-Business-Man"></i> {{$t('Customer_Info')}}</div>
            <div class="sd-client-name">{{sale.client_name}}</div>
            <div v-if="sale.client_phone" class="sd-line"><i class="i-Telephone"></i> <span dir="ltr">{{sale.client_phone}}</span></div>
            <div v-if="sale.client_email" class="sd-line"><i class="i-Email"></i> {{sale.client_email}}</div>
            <div v-if="sale.client_adr" class="sd-line"><i class="i-Map-Marker"></i> {{sale.client_adr}}</div>
          </div>
        </b-col>

        <!-- Totals -->
        <b-col lg="8" md="12" class="mb-4">
          <div class="sd-panel h-100">
            <div class="sd-panel-title"><i class="i-Receipt-3"></i> {{$t('Sale_Info')}}</div>
            <div class="sd-totals">
              <div class="sd-total-row"><span>{{$t('Number_of_Products')}}</span><span>{{details.length}} ({{ formatNumber(totalQty, 2) }} {{$t('Qty')}})</span></div>
              <div class="sd-total-row"><span>{{$t('SubTotal')}}</span><span>{{ money(linesTotal) }}</span></div>
              <div class="sd-total-row" v-if="toNum(sale.discount) > 0"><span>{{$t('Discount')}}</span><span>- {{ money(sale.discount) }}</span></div>
              <div class="sd-total-row" v-if="toNum(sale.TaxNet) > 0"><span>{{$t('OrderTax')}} ({{ sale.tax_rate }} %)</span><span>{{ money(sale.TaxNet) }}</span></div>
              <div class="sd-total-row" v-if="toNum(sale.shipping) > 0"><span>{{$t('Shipping')}}</span><span>{{ money(sale.shipping) }}</span></div>
              <div class="sd-total-row sd-grand"><span>{{$t('Total')}}</span><span>{{ money(sale.GrandTotal) }}</span></div>
              <div class="sd-words">
                <span class="sd-words-label">{{$t('Total_in_Words')}}</span>
                <span class="sd-words-text" :dir="totalInWords.dir">{{ totalInWords.text }}</span>
              </div>
            </div>
          </div>
        </b-col>
      </b-row>

      <!-- Products -->
      <div class="sd-panel mb-4">
        <div class="sd-panel-title"><i class="i-Shopping-Cart"></i> {{$t('ProductName')}} <span class="sd-count">{{details.length}}</span></div>
        <div class="table-responsive">
          <table class="table sd-table mb-0">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th>{{$t('ProductName')}}</th>
                <th class="text-center">{{$t('Qty')}}</th>
                <th class="text-center">{{$t('Price')}}</th>
                <th class="text-center">{{$t('Discount')}}</th>
                <th class="text-center">{{$t('Total')}}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(detail, index) in details" :key="index">
                <td class="text-center text-muted">{{index + 1}}</td>
                <td>
                  <div class="sd-product-name">{{detail.name}}</div>
                  <div class="sd-product-code">{{detail.code}}<span v-if="detail.is_imei && detail.imei_number"> · IMEI: {{detail.imei_number}}</span></div>
                </td>
                <td class="text-center">{{formatNumber(detail.quantity, 2)}} <small class="text-muted">{{detail.unit_sale}}</small></td>
                <td class="text-center">{{ money(detail.price) }}</td>
                <td class="text-center">
                  <span v-if="toNum(detail.DiscountNet) > 0" class="text-danger">- {{ money(detail.DiscountNet * detail.quantity) }}</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="text-center font-weight-bold">{{ money(detail.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="sale.note" class="sd-panel mb-4">
        <div class="sd-panel-title"><i class="i-Notepad"></i> {{$t('Note')}}</div>
        <div>{{sale.note}}</div>
      </div>

      <!-- Printable invoice (used by the Print button, not shown on screen) -->
      <div class="d-none">
        <div class="invoice" id="print_Invoice">
          <div class="invoice-print">
            <!-- Company Header -->
            <div class="text-center mb-4">
              <h3 class="font-weight-bold">{{sale.warehouse}}</h3>
              <hr style="border-top: 2px solid #000;">
            </div>
            
            <!-- Invoice Header Info -->
            <div class="d-flex justify-content-between mb-3">
              <div>
                <p><strong>{{$t('Invoice_num')}} </strong>: {{sale.Ref}}</p>
                <p v-if="sale.date"><strong>{{$t('date')}}</strong>: {{sale.date}}</p>
              </div>            
            </div>
            
            <!-- Client Info -->
            <div class="p-2 mb-3 border">
              <div class="col-md-6">
                <p class="m-0"><strong>{{$t('Client')}}</strong>: <strong>{{sale.client_name}}</strong></p>
                <p v-if="sale.client_adr" class="m-0"><strong>{{$t('Address')}}</strong>: {{sale.client_adr}}</p>
              </div>
            </div>
            
            <!-- Products Table -->
            <div class="table-responsive">
              <table class="table table-bordered">
                <thead>
                  <tr>
                    <th width="5%" class="text-center">#</th>
                    <th width="45%">{{$t('ProductName')}}</th>
                    <th width="15%" class="text-center">{{$t('Qty')}}</th>
                    <th width="15%" class="text-center">{{$t('Price')}}</th>
                    <th width="20%" class="text-center">{{$t('Total')}}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(detail, index) in details" :key="index">
                    <td class="text-center">{{index + 1}}</td>
                    <td>{{detail.name}}</td>
                    <td class="text-center">{{formatNumber(detail.quantity, 2)}}</td>
                    <td class="text-center">{{formatNumber(detail.price, 2)}}</td>
                    <td class="text-center">{{detail.total.toFixed(2)}}</td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr>
                    <td colspan="2" class="text-right">
                      <strong>{{$t('Number_of_Products')}}</strong>: {{details.length}}
                    </td>
                    <td colspan="2" class="text-right font-weight-bold">{{$t('Total')}}</td>
                    <td class="text-center font-weight-bold">{{sale.GrandTotal}}</td>
                  </tr>
                </tfoot>
              </table>
            </div>
            
            <!-- Total in Words -->
            <div class="border p-2 mb-3">
              <strong>{{$t('Total_in_Words')}}</strong>: {{sale.total_in_words}}
            </div>
            
            <!-- Payment Information -->
            <div class="row mt-4">            
              <div class="col-md-6">
                <table class="table table-bordered">
                  <tbody>
                    <tr>
                      <td><strong>{{$t('Total')}}</strong></td>
                      <td class="text-center">{{sale.GrandTotal ? sale.GrandTotal :  '0.00'}}</td>
                    </tr>
                    <tr>
                      <td><strong>{{$t('Due')}}</strong></td>
                      <td class="text-center">{{sale.due ? sale.due : '0.00'}}</td>
                    </tr>
                    <tr>
                      <td><strong>{{$t('Reste_credit')}}</strong></td>
                      <td class="text-center">{{sale.total_credit ? sale.total_credit : '0.00'}}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
            <div class="thanks row mt-4 justify-content-center">
              <p class="text-center">{{$t('Thank_You_For_Shopping_With_Us')}}</p>
            </div>
            
            <hr v-show="sale.note">
            <div v-show="sale.note" class="mt-3">
              <p><strong>{{$t('Note')}}</strong>: {{sale.note}}</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Profit modal -->
    <b-modal
      id="sale_profit_modal"
      hide-footer
      centered
      dialog-class="sale-profit-dialog"
      :title="$t('Sale_Profit_Title') + ' — ' + sale.Ref"
    >
      <div class="sale-detail sd-profit-body">
      <div v-if="profitLoading" class="text-center py-5">
        <div class="spinner spinner-primary"></div>
      </div>
      <div v-else-if="profit" class="sd-profit-body">
        <!-- Profit summary -->
        <div class="sd-cards sd-cards-modal">
          <div class="sd-card">
            <div class="sd-card-label">{{$t('Gross_Profit')}}</div>
            <div class="sd-card-value" :class="profit.summary.gross_profit < 0 ? 'text-danger' : 'text-success'">{{ money(profit.summary.gross_profit) }}</div>
            <div class="sd-card-sub text-muted">{{ profit.lines.length }} {{$t('ProductName')}}</div>
          </div>
          <div class="sd-card" v-if="profit.summary.order_discount > 0">
            <div class="sd-card-label">{{$t('Order_Discount')}}</div>
            <div class="sd-card-value text-danger">- {{ money(profit.summary.order_discount) }}</div>
          </div>
          <div class="sd-card sd-card-profit" :class="{'is-loss': profit.summary.net_profit < 0}">
            <div class="sd-card-label">{{$t('Net_Profit_Label')}}</div>
            <div class="sd-card-value">{{ money(profit.summary.net_profit) }}</div>
            <div class="sd-card-sub">{{$t('Margin')}} {{ profit.summary.margin }} %</div>
          </div>
        </div>

        <!-- Only the table scrolls: header and totals stay visible -->
        <div class="sd-profit-scroll">
          <table class="table sd-table mb-0">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th>{{$t('ProductName')}}</th>
                <th class="text-center">{{$t('Qty')}}</th>
                <th class="text-center">{{$t('Price')}}</th>
                <th class="text-center">{{$t('Unit_Cost')}}</th>
                <th class="text-center">{{$t('Unit_Profit')}}</th>
                <th class="text-center">{{$t('Show_Profit')}}</th>
                <th class="text-center">{{$t('Margin')}}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(line, index) in profit.lines" :key="index">
                <td class="text-center text-muted">{{index + 1}}</td>
                <td>
                  <div class="sd-product-name">{{line.name}}</div>
                  <div class="sd-product-code">{{line.code}}</div>
                </td>
                <td class="text-center text-nowrap">{{ formatNumber(line.quantity, 2) }} <small class="text-muted">{{line.unit}}</small></td>
                <td class="text-center text-nowrap">{{ money(line.unit_price) }}</td>
                <td class="text-center text-nowrap text-muted">{{ money(line.unit_cost) }}</td>
                <td class="text-center text-nowrap" :class="unitProfit(line) < 0 ? 'text-danger' : ''">{{ money(unitProfit(line)) }}</td>
                <td class="text-center text-nowrap font-weight-bold" :class="line.profit < 0 ? 'text-danger' : 'text-success'">{{ money(line.profit) }}</td>
                <td class="text-center">
                  <span class="sd-margin" :class="marginClass(line.margin)">{{ line.margin }} %</span>
                </td>
              </tr>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="6" class="font-weight-bold">{{$t('Gross_Profit')}}</td>
                <td class="text-center text-nowrap font-weight-bold" :class="profit.summary.gross_profit < 0 ? 'text-danger' : 'text-success'">{{ money(profit.summary.gross_profit) }}</td>
                <td></td>
              </tr>
              <tr v-if="profit.summary.order_discount > 0">
                <td colspan="6">{{$t('Order_Discount')}}</td>
                <td class="text-center text-nowrap text-danger">- {{ money(profit.summary.order_discount) }}</td>
                <td></td>
              </tr>
              <tr class="sd-profit-total">
                <td colspan="6" class="font-weight-bold">{{$t('Net_Profit_Label')}}</td>
                <td class="text-center text-nowrap font-weight-bold" :class="profit.summary.net_profit < 0 ? 'text-danger' : 'text-success'">{{ money(profit.summary.net_profit) }}</td>
                <td class="text-center"><span class="sd-margin" :class="marginClass(profit.summary.margin)">{{ profit.summary.margin }} %</span></td>
              </tr>
            </tfoot>
          </table>
        </div>
        <p class="sd-note mt-3 mb-0"><i class="i-Information"></i> {{$t('Profit_Cost_Note')}}</p>
      </div>
      </div>
    </b-modal>
  </div>
</template>


<script>

import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  computed: {
    ...mapGetters(["currentUserPermissions", "currentUser"]),

    linesTotal() {
      return this.details.reduce((sum, d) => sum + this.toNum(d.total), 0);
    },

    totalQty() {
      return this.details.reduce((sum, d) => sum + this.toNum(d.quantity), 0);
    },

    // Amount in words in the user's language (the printed invoice stays in Arabic)
    totalInWords() {
      const all = this.sale.total_in_words_i18n || {};
      const locale = this.$i18n.locale;
      const text = all[locale] || all.en || this.sale.total_in_words || "";
      const isArabic = !all[locale] ? !all.en : locale === "ar";
      return { text: text, dir: isArabic ? "rtl" : "ltr" };
    }
  },
  metaInfo: {
    title: "Detail Sale"
  },

  data() {
    return {
      isLoading: true,
      sale: {},
      details: [],
      variants: [],
      invoiceDirection: "ltr",
      profit: null,
      profitLoading: false
    };
  },

  methods: {

    toNum(value) {
      const n = parseFloat(value);
      return isNaN(n) ? 0 : n;
    },

    money(value) {
      const n = this.toNum(value);
      const formatted = n.toLocaleString("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
      return (this.currentUser && this.currentUser.currency ? this.currentUser.currency + " " : "") + formatted;
    },

    statusLabel(statut) {
      if (statut === "completed") return this.$t("complete");
      if (statut === "pending") return this.$t("Pending");
      if (statut === "ordered") return this.$t("Ordered");
      return statut;
    },

    statusClass(statut) {
      if (statut === "completed") return "chip-success";
      if (statut === "pending") return "chip-info";
      return "chip-warning";
    },

    paymentLabel(status) {
      if (status === "paid") return this.$t("Paid");
      if (status === "partial") return this.$t("partial");
      return this.$t("Unpaid");
    },

    paymentClass(status) {
      if (status === "paid") return "chip-success";
      if (status === "partial") return "chip-primary";
      return "chip-warning";
    },

    unitProfit(line) {
      return Math.round((this.toNum(line.unit_price) - this.toNum(line.unit_cost)) * 100) / 100;
    },

    marginClass(margin) {
      if (margin < 0) return "margin-loss";
      if (margin < 15) return "margin-low";
      return "margin-good";
    },

    //------------------------------ Profit of the sale -------------------------\\
    Show_Profit() {
      this.profit = null;
      this.profitLoading = true;
      this.$bvModal.show("sale_profit_modal");
      axios
        .get(`sales/profit/${this.$route.params.id}`)
        .then(response => {
          this.profit = response.data;
          this.profitLoading = false;
        })
        .catch(() => {
          this.profitLoading = false;
          this.$bvModal.hide("sale_profit_modal");
          this.makeToast("danger", this.$t("InvalidData"), this.$t("Failed"));
        });
    },
   

    //----------------------------------- Invoice Sale PDF  -------------------------\\
    Sale_PDF() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
     
       axios
        .get(`sale_pdf/${id}`, {
          responseType: "blob", // important
          headers: {
            "Content-Type": "application/json"
          }
        })
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute("download", "Sale_" + this.sale.Ref + ".pdf");
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

     //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
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

    //------------------------------ Print -------------------------\\
    print() {
      // Create a new window
      const printWindow = window.open('', '_blank');
      
      // Get the invoice content
      const invoiceContent = document.getElementById('print_Invoice').innerHTML;
      
      // Write a simple RTL document
      printWindow.document.write(`
        <!DOCTYPE html>
        <html dir="rtl">
        <head>
          <title>Invoice</title>
          <style>
            body {
              font-family: Arial, sans-serif;
              direction: rtl;
              text-align: right;
              padding: 10mm;
              margin: 0;
              font-size: 12px;
              background-color: white;
            }
            h3, h4 {
              margin: 5px 0;
            }
            p {
              margin: 5px 0;
            }
            [dir="ltr"] {
              direction: ltr;
              unicode-bidi: isolate;
            }
            .phone-number {
              direction: ltr;
              display: inline-block;
            }
            .text-center {
              text-align: center;
            }
            .text-right {
              text-align: right;
            }
            .text-left {
              text-align: left;
            }
            .font-weight-bold {
              font-weight: bold;
            }
            hr {
              border-top: 1px solid #000;
              margin: 5px 0;
            }
            .mb-4 {
              margin-bottom: 15px;
            }
            .mb-3 {
              margin-bottom: 10px;
            }
            .mt-2 {
              margin-top: 10px;
            }
            .mt-3 {
              margin-top: 10px;
            }
            .mt-4 {
              margin-top: 15px;
            }
            .p-2 {
              padding: 8px;
            }
            .p-3 {
              padding: 10px;
            }
            .border {
              border: 1px solid #000;
            }
            .row {
              display: flex;
              flex-wrap: wrap;
            }
            .col-md-6 {
              flex: 0 0 50%;
              max-width: 50%;
              box-sizing: border-box;
            }
            .d-flex {
              display: flex;
            }
            .justify-content-between {
              justify-content: space-between;
            }
            .table-responsive {
              width: 100%;
            }
            table {
              width: 100%;
              border-collapse: collapse;
              margin-bottom: 10px;
            }
            table, th, td {
              border: 1px solid #000;
            }
            th, td {
              padding: 5px;
              text-align: right;
            }
            thead {
              background-color: #f5f5f5;
            }
            tfoot {
              font-weight: bold;
              background-color: #f9f9f9;
            }
            .thanks {
              justify-content: center;
            }
            @media print {
              body {
                padding: 5mm;
                font-size: 11px;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
              }
              table, th, td {
                border: 1px solid #000 !important;
              }
              .no-print {
                display: none;
              }
            }
          </style>
        </head>
        <body>
          ${invoiceContent}
        </body>
        </html>
      `);
      
      // Close the document and focus on it
      printWindow.document.close();
      printWindow.focus();
      
      // Print after a short delay
      setTimeout(() => {
        printWindow.print();
        printWindow.close();
      }, 1000);
    },


    Send_Email() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
      axios
        .post("sales_send_email", {
          id: id,
        })
        .then(response => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast(
            "success",
            this.$t("Send.TitleEmail"),
            this.$t("Success")
          );
        })
        .catch(error => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast("danger", this.$t("SMTPIncorrect"), this.$t("Failed"));
        });
    },

    //---------SMS notification
     Sale_SMS() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
      axios
        .post("sales_send_sms", {
          id: id,
        })
        .then(response => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast(
            "success",
            this.$t("Send_SMS"),
            this.$t("Success")
          );
        })
        .catch(error => {
          // Complete the animation of the  progress bar.
          setTimeout(() => NProgress.done(), 500);
          this.makeToast("danger", this.$t("sms_config_invalid"), this.$t("Failed"));
        });
    },

    //----------------------------------- Get Details Sale ------------------------------\\
    Get_Details() {
      let id = this.$route.params.id;
      axios
        .get(`sales/${id}`)
        .then(response => {
          this.sale = response.data.sale;
          this.details = response.data.details;
          this.company = response.data.company;
          this.isLoading = false;
        })
        .catch(response => {
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    //------------------------------------------ DELETE Sale ------------------------------\\
    Delete_Sale() {
      let id = this.$route.params.id;
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
          axios
            .delete("sales/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete.Deleted"),
                this.$t("Delete.SaleDeleted"),
                "success"
              );
              this.$router.push({ name: "index_sales" });
            })
            .catch(() => {
              this.$swal(
                this.$t("Delete.Failed"),
                this.$t("Delete.Therewassomethingwronge"),
                "warning"
              );
            });
        }
      });
    }
  }, //end Methods

  //----------------------------- Created function-------------------

  created: function() {
    this.Get_Details();
  }
};
</script>

<style scoped src="../shared/detail_page.css"></style>

<!-- Not scoped: the modal dialog element is created by bootstrap-vue (the theme CSS has no .modal-xl) -->
<style>
.sale-profit-dialog {
  max-width: 1100px;
  width: calc(100% - 32px);
  margin-left: auto;
  margin-right: auto;
}
/* Fixed height: header + cards stay put, the products table scrolls inside */
.sale-profit-dialog .modal-content {
  height: calc(100vh - 3.5rem);
  max-height: calc(100vh - 3.5rem);
}
.sale-profit-dialog .modal-body {
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}
@media (max-width: 576px) {
  .sale-profit-dialog .modal-content {
    height: calc(100vh - 1rem);
    max-height: calc(100vh - 1rem);
  }
}
</style>
