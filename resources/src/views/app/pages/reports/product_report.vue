<template>
  <div class="main-content">
    <breadcumb :page="$t('product_report')" :folder="$t('Reports')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <b-col md="12" class="text-center" v-if="!isLoading">
      <date-range-picker 
        v-model="dateRange" 
        :startDate="startDate" 
        :endDate="endDate" 
        @update="Submit_filter_dateRange"
        :locale-data="locale"> 

        <template v-slot:input="picker" style="min-width: 350px;">
          {{ picker.startDate.toJSON().slice(0, 10)}} - {{ picker.endDate.toJSON().slice(0, 10)}}
        </template>        
      </date-range-picker>
    </b-col>

    <div class="d-flex justify-content-start align-items-baseline my-3">

      <b-button @click="export_PDF()" size="sm" variant="outline-success ripple m-1">
        <i class="i-File-Copy"></i> {{$t('Imprimer_en_PDF')}}
      </b-button>

      <b-form-group :label="$t('warehouse')" class="mb-0 w-25">
        <v-select
          @input="Selected_Warehouse"
          v-model="warehouse_id"
          :reduce="label => label.value"
          :placeholder="$t('Choose_Warehouse')"
          :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
        />
      </b-form-group>
    </div>

    <vue-good-table
      v-if="!isLoading"
      mode="remote"
      :columns="columns"
      :totalRows="totalRows"
      :rows="rows"
      :group-options="{
        enabled: true,
        headerPosition: 'bottom',
      }"
      @on-page-change="onPageChange"
      @on-per-page-change="onPerPageChange"
      @on-search="onSearch_products"
      :search-options="{
        placeholder: $t('Search_this_table'),
        enabled: true,
      }"
      :pagination-options="{
        enabled: true,
        mode: 'records',
        nextLabel: 'next',
        prevLabel: 'prev',
      }"
      styleClass="mt-5 table-hover tableOne vgt-table"
    >
      <template slot="table-actions" class="mt-2 mb-3"></template>
      <template slot="table-row" slot-scope="props">
        <span v-if="props.column.field == 'actions'">
          <router-link title="Report" :to="'/app/reports/detail_product/'+props.row.id">
            <b-button variant="primary">{{$t('Reports')}}</b-button>
          </router-link>
        </span>

        <div v-else-if="props.column.field == 'sold_amount'">
          <span>{{currentUser.currency}} {{props.row.sold_amount}}</span>
        </div>
      </template>
    </vue-good-table>
  </div>
</template>

<script>
import NProgress from "nprogress";
import { mapGetters } from "vuex";
import DateRangePicker from 'vue2-daterange-picker'
//you need to import the CSS manually
import 'vue2-daterange-picker/dist/vue2-daterange-picker.css'
import moment from 'moment'
import jsPDF from "jspdf";
import "jspdf-autotable";

export default {
  metaInfo: {
    title: "Products Report"
  },
  components: { DateRangePicker },
  data() {
    return {
      isLoading: true,
      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      limit: "10",
      totalRows: "",
      products: [],
      warehouses: [],
      rows: [{
        children: [],
      },],
      warehouse_id: "",
      search_products:"",
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
    columns() {
      return [
        {
          label: this.$t("ProductCode"),
          field: "code",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("ProductName"),
          field: "name",
          tdClass: "text-left",
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("TotalSales"),
          field: "sold_qty",
          tdClass: "text-left",
          headerField: this.sumTotalSales,
          thClass: "text-left",
          sortable: false
        },

        {
          label: this.$t("TotalAmount"),
          field: "sold_amount",
          tdClass: "text-left",
          headerField: this.sumTotalAmount,
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Action"),
          field: "actions",
          html: true,
          tdClass: "text-right",
          thClass: "text-right",
          sortable: false
        }
      ];
    }
  },

  methods: {

    sumTotalSales(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) {
        console.error('Invalid input for sumTotalSales');
        return 0;
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
          let qty = parseFloat(rowObj.children[i].sold_qty.split(" ")[0]);
          sum += qty;
      }
      return sum;
    },

    sumTotalAmount(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) {
        console.error('Invalid input for Total Amount');
        return 0;
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
        if (typeof rowObj.children[i].sold_amount === 'number') {
          sum += rowObj.children[i].sold_amount;
        } else {
          console.error('Invalid Total Amount at index', i);
        }
      }
      return sum;
    },

    onSearch_products(value) {
      this.search_products = value.searchTerm;
      this.Get_products_report(1);
    },


    //----------------------------------- Export PDF ------------------------------\\
    export_PDF() {
    var self = this;
    let pdf = new jsPDF("p", "pt");

    // Title customization
    pdf.setFontSize(18); // Set font size
    pdf.setFont("helvetica", "bold"); // Set font style to bold
    const titleText = "Rapport des produits";
    const titleX = pdf.internal.pageSize.getWidth() / 2;
    const titleY = 25;
    pdf.text(titleText, titleX, titleY, { align: "center" });

    // Current date and time
    const currentDate = new Date().toLocaleString(); // Get current date and time in string format

    // Title with date and time
    pdf.setFontSize(12); // Set font size for date and time
    pdf.text(currentDate, titleX, titleY + 20, { align: "center" });
    
    // Columns for the table
    let columns = [
      { title: "N° produit", dataKey: "code" },
      { title: "Produit", dataKey: "name" },
      { title: "Qte vendu", dataKey: "sold_qty" },
      { title: "Total", dataKey: "sold_amount" },
    ];
    
    // Calculate totals
    let totalQty = 0;
    let totalAmount = 0;
    self.products.forEach(product => {
      totalQty += parseFloat(product.sold_qty.split(" ")[0]);
      totalAmount += parseFloat(product.sold_amount);
    });
    
    // Data for the table
    let data = self.products.slice(); // Copy the products array
    
    // Add totals as the last row
    data.push({
      code: "",
      name: "Total",
      sold_qty: { content: totalQty.toString() + " pc", styles: { font: 'helvetica', fontStyle: 'bold' } },
      sold_amount: { content: totalAmount.toFixed(2) + " dh", styles: { font: 'helvetica', fontStyle: 'bold' } }
    });
    
    pdf.autoTable(columns, data, {
      startY: 60,
      theme: 'grid'
    });
    // Generate filename with current date
    const currentDate_title = new Date().toISOString().split('T')[0];
    const filename = `Rapport_produits_${currentDate_title}.pdf`;

    // Save the PDF with the generated filename
    pdf.save(filename);

  },

    //---- update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_products_report(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_products_report(1);
      }
    },

   //----------------------------- Submit Date Picker -------------------\\
   Submit_filter_dateRange() {
      var self = this;
      self.startDate =  self.dateRange.startDate.toJSON().slice(0, 10);
      self.endDate = self.dateRange.endDate.toJSON().slice(0, 10);
      self.Get_products_report(1);
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

    //---------------------- Event Select Warehouse ------------------------------\\
    Selected_Warehouse(value) {
      if (value === null) {
        this.warehouse_id = "";
      }
      this.Get_products_report(1);
    },


    //----------------------------- Get_products_report------------------\\
    Get_products_report(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.get_data_loaded();

      axios
        .get(
          "report/product_report?page=" +
            page +
            "&limit=" +
            this.limit +
            "&warehouse_id=" +
            this.warehouse_id +
            "&to=" +
            this.endDate +
            "&from=" +
            this.startDate +
            "&search=" +
            this.search_products
        )
        .then(response => {
          this.warehouses = response.data.warehouses;
          this.products = response.data.products;
          this.totalRows = response.data.totalRows;
          this.rows[0].children = this.products;
          // Complete the animation of theprogress bar.
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
    }
  }, //end Methods

  //----------------------------- Created function------------------- \\

  created: function() {
    this.Get_products_report(1);
  }
};
</script>
