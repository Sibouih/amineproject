<template>
  <div class="main-content sale-detail">
    <breadcumb :page="$t('PurchaseDetail')" :folder="$t('ListPurchases')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <div v-if="!isLoading">
      <!-- Header -->
      <div class="sd-header">
        <div class="sd-header-main">
          <div class="sd-ref">
            <span class="sd-ref-label">{{$t('Reference')}}</span>
            <h3 class="sd-ref-value">{{purchase.Ref}}</h3>
          </div>
          <div class="sd-chips">
            <span class="sd-chip" :class="statusClass(purchase.statut)">{{ statusLabel(purchase.statut) }}</span>
            <span class="sd-chip" :class="paymentClass(purchase.payment_status)">{{ paymentLabel(purchase.payment_status) }}</span>
          </div>
          <div class="sd-meta">
            <span><i class="i-Calendar-4"></i> {{purchase.date}}</span>
            <span><i class="i-Building"></i> {{purchase.warehouse}}</span>
          </div>
        </div>

        <div class="sd-actions">
          <router-link
            v-if="currentUserPermissions && currentUserPermissions.includes('Purchases_edit') && purchase.purchase_has_return == 'no'"
            class="btn btn-sm sd-btn sd-btn-edit"
            :to="{ name:'edit_purchase', params: { id: $route.params.id } }"
          >
            <i class="i-Edit"></i> {{$t('EditPurchase')}}
          </router-link>
          <button @click="print()" class="btn btn-sm sd-btn sd-btn-print">
            <i class="i-Billing"></i> {{$t('print')}}
          </button>
          <button @click="Print_Purchase_PDF()" class="btn btn-sm sd-btn sd-btn-light">
            <i class="i-File-TXT"></i> PDF
          </button>
          <button @click="Send_Email()" class="btn btn-sm sd-btn sd-btn-light">
            <i class="i-Envelope-2"></i> {{$t('Email')}}
          </button>
          <button @click="Purchase_SMS()" class="btn btn-sm sd-btn sd-btn-light">
            <i class="i-Speach-Bubble"></i> SMS
          </button>
          <button
            v-if="currentUserPermissions && currentUserPermissions.includes('Purchases_delete') && purchase.purchase_has_return == 'no'"
            @click="Delete_Purchase()"
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
          <div class="sd-card-value">{{ money(purchase.GrandTotal) }}</div>
        </div>
        <div class="sd-card">
          <div class="sd-card-label">{{$t('Amount_paid')}}</div>
          <div class="sd-card-value text-success">{{ money(purchase.paid_amount) }}</div>
        </div>
        <div class="sd-card">
          <div class="sd-card-label">{{$t('Due')}}</div>
          <div class="sd-card-value" :class="{'text-danger': toNum(purchase.due) > 0}">{{ money(purchase.due) }}</div>
        </div>
        <div class="sd-card">
          <div class="sd-card-label">{{$t('Reste_credit')}}</div>
          <div class="sd-card-value" :class="{'text-danger': toNum(purchase.total_credit) > 0}">{{ money(purchase.total_credit) }}</div>
        </div>
      </div>

      <b-row>
        <!-- Supplier -->
        <b-col lg="4" md="12" class="mb-4">
          <div class="sd-panel h-100">
            <div class="sd-panel-title"><i class="i-Business-Man"></i> {{$t('Supplier_Info')}}</div>
            <div class="sd-client-name">{{purchase.supplier_name}}</div>
            <div v-if="purchase.supplier_phone" class="sd-line"><i class="i-Telephone"></i> <span dir="ltr">{{purchase.supplier_phone}}</span></div>
            <div v-if="purchase.supplier_email" class="sd-line"><i class="i-Email"></i> {{purchase.supplier_email}}</div>
            <div v-if="purchase.supplier_adr" class="sd-line"><i class="i-Map-Marker"></i> {{purchase.supplier_adr}}</div>
          </div>
        </b-col>

        <!-- Totals -->
        <b-col lg="8" md="12" class="mb-4">
          <div class="sd-panel h-100">
            <div class="sd-panel-title"><i class="i-Receipt-3"></i> {{$t('Purchase_Info')}}</div>
            <div class="sd-totals">
              <div class="sd-total-row"><span>{{$t('Number_of_Products')}}</span><span>{{details.length}} ({{ formatNumber(totalQty, 2) }} {{$t('Qty')}})</span></div>
              <div class="sd-total-row"><span>{{$t('SubTotal')}}</span><span>{{ money(linesTotal) }}</span></div>
              <div class="sd-total-row" v-if="toNum(purchase.discount) > 0"><span>{{$t('Discount')}}</span><span>- {{ money(purchase.discount) }}</span></div>
              <div class="sd-total-row" v-if="toNum(purchase.TaxNet) > 0"><span>{{$t('OrderTax')}} ({{ purchase.tax_rate }} %)</span><span>{{ money(purchase.TaxNet) }}</span></div>
              <div class="sd-total-row" v-if="toNum(purchase.shipping) > 0"><span>{{$t('Shipping')}}</span><span>{{ money(purchase.shipping) }}</span></div>
              <div class="sd-total-row sd-grand"><span>{{$t('Total')}}</span><span>{{ money(purchase.GrandTotal) }}</span></div>
            </div>
          </div>
        </b-col>
      </b-row>

      <!-- Products -->
      <div class="sd-panel mb-4">
        <div class="sd-panel-title"><i class="i-Shopping-Cart"></i> {{$t('Order_Summary')}} <span class="sd-count">{{details.length}}</span></div>
        <div class="table-responsive">
          <table class="table sd-table mb-0">
            <thead>
              <tr>
                <th class="text-center">#</th>
                <th>{{$t('ProductName')}}</th>
                <th class="text-center">{{$t('Quantity')}}</th>
                <th class="text-center">{{$t('Unitcost')}}</th>
                <th class="text-center">{{$t('Discount')}}</th>
                <th class="text-center">{{$t('Tax')}}</th>
                <th class="text-center">{{$t('SubTotal')}}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(detail, index) in details" :key="index">
                <td class="text-center text-muted">{{index + 1}}</td>
                <td>
                  <div class="sd-product-name">{{detail.name}}</div>
                  <div class="sd-product-code">{{detail.code}}<span v-if="detail.is_imei && detail.imei_number"> · {{$t('IMEI_SN')}}: {{detail.imei_number}}</span></div>
                </td>
                <td class="text-center text-nowrap">{{formatNumber(detail.quantity, 2)}} <small class="text-muted">{{detail.unit_purchase}}</small></td>
                <td class="text-center text-nowrap">{{ money(detail.cost) }}</td>
                <td class="text-center text-nowrap">
                  <span v-if="toNum(detail.DiscountNet) > 0" class="text-danger">- {{ money(detail.DiscountNet * detail.quantity) }}</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="text-center text-nowrap">
                  <span v-if="toNum(detail.taxe) > 0">{{ money(detail.taxe * detail.quantity) }}</span>
                  <span v-else class="text-muted">—</span>
                </td>
                <td class="text-center text-nowrap font-weight-bold">{{ money(detail.total) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div v-if="purchase.note" class="sd-panel mb-4">
        <div class="sd-panel-title"><i class="i-Notepad"></i> {{$t('Note')}}</div>
        <div>{{purchase.note}}</div>
      </div>

      <!-- Printable invoice (used by the Print button, not shown on screen) -->
      <div class="d-none">
        <div class="invoice mt-5" id="print_Invoice">
          <div class="invoice-print">
            <b-row class="justify-content-md-center">
              <h4 class="font-weight-bold">{{$t('PurchaseDetail')}} : {{purchase.Ref}}</h4>
            </b-row>
            <hr>
            <b-row class="mt-5">
              <b-col lg="4" md="4" sm="12" class="mb-4">
                <h5 class="font-weight-bold">{{$t('Supplier_Info')}}</h5>
                <div>{{purchase.supplier_name}}</div>
                <div>{{purchase.supplier_email}}</div>
                <div>{{purchase.supplier_phone}}</div>
                <div>{{purchase.supplier_adr}}</div>
              </b-col>
              <b-col lg="4" md="4" sm="12" class="mb-4">
                <h5 class="font-weight-bold">{{$t('Company_Info')}}</h5>
                <div>{{company.CompanyName}}</div>
                <div>{{company.email}}</div>
                <div>{{company.CompanyPhone}}</div>
                <div>{{company.CompanyAdress}}</div>
              </b-col>
              <b-col lg="4" md="4" sm="12" class="mb-4">
                <h5 class="font-weight-bold">{{$t('Purchase_Info')}}</h5>
                <div>{{$t('Reference')}} : {{purchase.Ref}}</div>
                <div>
                  {{$t('Status')}} :
                  <span
                    v-if="purchase.statut == 'received'"
                    class="badge badge-outline-success"
                  >{{$t('Received')}}</span>
                  <span
                    v-else-if="purchase.statut == 'pending'"
                    class="badge badge-outline-info"
                  >{{$t('Pending')}}</span>
                  <span v-else class="badge badge-outline-warning">{{$t('Ordered')}}</span>
                </div>
                <div>{{$t('warehouse')}} : {{purchase.warehouse}}</div>
                <div>
                  {{$t('PaymentStatus')}} :
                  <span
                    v-if="purchase.payment_status == 'paid'"
                    class="badge badge-outline-success"
                  >{{$t('Paid')}}</span>
                  <span
                    v-else-if="purchase.payment_status == 'partial'"
                    class="badge badge-outline-primary"
                  >{{$t('partial')}}</span>
                  <span v-else class="badge badge-outline-warning">{{$t('Unpaid')}}</span>
                </div>
              </b-col>
            </b-row>
            <b-row class="mt-3">
              <b-col md="12">
                <h5 class="font-weight-bold">{{$t('Order_Summary')}}</h5>
                <div class="table-responsive">
                  <table class="table table-hover table-md">
                    <thead class="bg-gray-300">
                      <tr>
                        <th scope="col">{{$t('ProductName')}}</th>
                        <th scope="col">{{$t('Net_Unit_Cost')}}</th>
                        <th scope="col">{{$t('Quantity')}}</th>
                        <th scope="col">{{$t('Unitcost')}}</th>
                        <th scope="col">{{$t('Discount')}}</th>
                        <th scope="col">{{$t('SubTotal')}}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="detail in details">
                        <td><span>{{detail.code}} ({{detail.name}})</span>
                          <p v-show="detail.is_imei && detail.imei_number !==null ">{{$t('IMEI_SN')}} : {{detail.imei_number}}</p>
                        </td>
                        <td>{{currentUser.currency}} {{formatNumber(detail.Net_cost,3)}}</td>
                        <td>{{formatNumber(detail.quantity,2)}} {{detail.unit_purchase}}</td>
                        <td>{{currentUser.currency}} {{formatNumber(detail.cost,2)}}</td>
                        <td>{{currentUser.currency}} {{formatNumber(detail.DiscountNet,2)}}</td>
                        <td>{{currentUser.currency}} {{detail.total.toFixed(2)}}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </b-col>
              <div class="offset-md-9 col-md-3 mt-4">
                <table class="table table-striped table-sm">
                  <tbody>
                    <tr>
                      <td class="bold">{{$t('Discount')}}</td>
                      <td>{{currentUser.currency}} {{purchase.discount.toFixed(2)}}</td>
                    </tr>
                    <tr>
                      <td class="bold">{{$t('Shipping')}}</td>
                      <td>{{currentUser.currency}} {{purchase.shipping.toFixed(2)}}</td>
                    </tr>
                    <tr>
                      <td>
                        <span class="font-weight-bold">{{$t('Total')}}</span>
                      </td>
                      <td>
                        <span
                          class="font-weight-bold"
                        >{{currentUser.currency}} {{purchase.GrandTotal}}</span>
                      </td>
                    </tr>
                    <tr>
                      <td>
                        <span class="font-weight-bold">{{$t('Paid')}}</span>
                      </td>
                      <td>
                        <span
                          class="font-weight-bold"
                        >{{currentUser.currency}} {{purchase.paid_amount}}</span>
                      </td>
                    </tr>
                    <tr>
                      <td>
                        <span class="font-weight-bold">{{$t('Due')}}</span>
                      </td>
                      <td>
                        <span
                          class="font-weight-bold"
                        >{{currentUser.currency}} {{purchase.due}}</span>
                      </td>
                    </tr>
                    <tr>
                      <td>
                        <span class="font-weight-bold">{{$t('Reste_credit')}}</span>
                      </td>
                      <td>
                        <span
                          
                          class="font-weight-bold"
                        >{{currentUser.currency}} {{purchase.total_credit}}</span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </b-row>
            <hr v-show="purchase.note">
            <b-row class="mt-4">
             <b-col md="12">
               <p>{{purchase.note}}</p>
             </b-col>
          </b-row>
          </div>
        </div>
      </div>
    </div>
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
    }
  },
  metaInfo: {
    title: "Detail Purchase"
  },

  data() {
    return {
      isLoading: true,
      purchase: {},
      details: [],
      variants: [],
      company: {},
      email: {
        to: "",
        subject: "",
        message: "",
        supplier_name: "",
        Purchase_Ref: ""
      }
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
      if (statut === "received") return this.$t("Received");
      if (statut === "pending") return this.$t("Pending");
      return this.$t("Ordered");
    },

    statusClass(statut) {
      if (statut === "received") return "chip-success";
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
    //----------------------------------- print Purchase -------------------------\\
    Print_Purchase_PDF() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
      axios
        .get(`purchase_pdf/${id}`, {
          responseType: "blob", // important
          headers: {
            "Content-Type": "application/json"
          }
        })
     
        .then(response => {
          const url = window.URL.createObjectURL(new Blob([response.data]));
          const link = document.createElement("a");
          link.href = url;
          link.setAttribute(
            "download",
            "purchase_" + this.purchase.Ref + ".pdf"
          );
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

    //------------------------------ Print -------------------------\\
    print() {
      this.$htmlToPaper('print_Invoice');
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

    //----------------------------------- Get Details Purchase ------------------------------\\
    Get_Details() {
      let id = this.$route.params.id;
      axios
        .get(`purchases/${id}`)
        .then(response => {
          this.purchase = response.data.purchase;
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

    //---------------------------------------------------- Purchase Email -------------------------------\\
    // purchase_Email(purchase) {
    //   this.email.to = this.purchase.supplier_email;
    //   this.email.Purchase_Ref = this.purchase.Ref;
    //   this.email.supplier_name = this.purchase.supplier_name;
    //   this.Send_Email();
    // },

    //--------------------------------------------- Send Purchase to Email -------------------------------\\
    Send_Email() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
      axios
        .post("purchase_send_email", {
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
     
     Purchase_SMS() {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      let id = this.$route.params.id;
      axios
        .post("purchase_send_sms", {
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

      //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },


    //------- Remove Purchase

    Delete_Purchase() {
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
            .delete("purchases/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete.Deleted"),
                this.$t("Delete.PurchaseDeleted"),
                "success"
              );
              this.$router.push({ name: "index_purchases" });
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
