<template>
  <div class="main-content">
    <breadcumb :page="$t('SaleDetail')" :folder="$t('Sales')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>

    <b-card v-if="!isLoading">
      <b-row>
        <b-col md="12" class="mb-5">

          <router-link
            v-if="currentUserPermissions && currentUserPermissions.includes('Sales_edit') && sale.sale_has_return == 'no'"
            title="Edit"
            class="btn btn-success btn-icon ripple btn-sm"
            :to="{ name:'edit_sale', params: { id: $route.params.id } }"
          >
            <i class="i-Edit"></i>
            <span>{{$t('EditSale')}}</span>
          </router-link>
           <button @click="Sale_SMS()" class="btn btn-secondary btn-icon ripple btn-sm">
            <i class="i-Speach-Bubble"></i>
            SMS
          </button>          
          <button @click="print()" class="btn btn-warning btn-icon ripple btn-sm">
            <i class="i-Billing"></i>
            {{$t('print')}}
          </button>
          <button
            v-if="currentUserPermissions && currentUserPermissions.includes('Sales_delete') && sale.sale_has_return == 'no'"
            @click="Delete_Sale()"
            class="btn btn-danger btn-icon ripple btn-sm"
          >
            <i class="i-Close-Window"></i>
            {{$t('Del')}}
          </button>
        </b-col>
      </b-row>
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
                  <th width="5%" class="text-center">N°</th>
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
    </b-card>
  </div>
</template>

<script>

import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";

export default {
  computed: mapGetters(["currentUserPermissions", "currentUser"]),
  metaInfo: {
    title: "Detail Sale"
  },

  data() {
    return {
      isLoading: true,
      sale: {},
      details: [],
      variants: [],
      invoiceDirection: "ltr"
    };
  },

  methods: {
   

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