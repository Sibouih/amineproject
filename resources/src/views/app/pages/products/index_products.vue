<template>
  <div class="main-content">
    <breadcumb :page="$t('productsList')" :folder="$t('Products')"/>
    <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
    <div v-else>
      <vue-good-table
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
        @on-sort-change="onSortChange"
        @on-search="onSearch"
        :select-options="{ 
          enabled: true ,
          clearSelectionText: '',
        }"
        @on-selected-rows-change="selectionChanged"
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
        styleClass="tableOne vgt-table"
      >
        <div slot="selected-row-actions">
          <button class="btn btn-danger" @click="delete_by_selected()">{{$t('Del')}}</button>
        </div>
        <div slot="table-actions" class="mt-2 mb-3">
          <v-select
                @input="Selected_Warehouse"
                v-model="warehouse_id"
                :reduce="label => label.value"
                :placeholder="$t('Choose_Warehouse')"
                :options="warehouses.map(warehouses => ({label: warehouses.name, value: warehouses.id}))"
          />
          <router-link
            class="btn-sm btn btn-primary btn-icon m-1"
            v-if="currentUserPermissions && currentUserPermissions.includes('products_add')"
            to="/app/products/store"
          >
            <span class="ul-btn__icon">
              <i class="i-Add"></i>
            </span>
            <span class="ul-btn__text ml-1">{{$t('Add')}}</span>
          </router-link>
          <b-button @click="Product_PDF()" size="sm" variant="outline-success m-1">
            <i class="i-File-Copy"></i> PDF
          </b-button>
          <vue-excel-xlsx
              class="btn btn-sm btn-outline-danger ripple m-1"
              :data="products"
              :columns="columns"
              :file-name="'products'"
              :file-type="'xlsx'"
              :sheet-name="'products'"
              >
              <i class="i-File-Excel"></i> EXCEL
          </vue-excel-xlsx>
          <b-button variant="outline-info m-1" size="sm" v-b-toggle.sidebar-right>
            <i class="i-Filter-2"></i>
            {{ $t("Filter") }}
          </b-button>
        </div>

        <template slot="table-row" slot-scope="props">
          <span v-if="props.column.field == 'actions'">
            <router-link
              v-if="currentUserPermissions && currentUserPermissions.includes('products_view')"
              v-b-tooltip.hover
              title="View"
              :to="{ name:'detail_product', params: { id: props.row.id} }"
            >
              <i class="i-Eye text-25 text-info"></i>
            </router-link>
            <router-link
              v-if="currentUserPermissions && currentUserPermissions.includes('products_edit')"
              v-b-tooltip.hover
              title="Edit"
              :to="{ name:'edit_product', params: { id: props.row.id } }"
            >
              <i class="i-Edit text-25 text-success"></i>
            </router-link>
            <a
              v-if="currentUserPermissions && currentUserPermissions.includes('products_delete')"
              @click="Remove_Product(props.row.id)"
              v-b-tooltip.hover
              title="Delete"
              class="cursor-pointer"
            >
              <i class="i-Close-Window text-25 text-danger"></i>
            </a>
          </span>
          <span v-else-if="props.column.field == 'image'">
            <b-img
              thumbnail
              height="50"
              width="50"
              fluid
              :src="'/images/products/' + props.row.image"
              alt="image"
            ></b-img>
          </span>
        </template>
      </vue-good-table>

      <!-- Multiple filter -->
      <b-sidebar id="sidebar-right" :title="$t('Filter')" bg-variant="white" right shadow>
        <div class="px-3 py-2">
          <b-row>
            <!-- Code Product  -->
            <b-col md="12">
              <b-form-group :label="$t('CodeProduct')">
                <b-form-input
                  label="Code Product"
                  :placeholder="$t('SearchByCode')"
                  v-model="Filter_code"
                ></b-form-input>
              </b-form-group>
            </b-col>

            <!-- Name  -->
            <b-col md="12">
              <b-form-group :label="$t('ProductName')">
                <b-form-input
                  label="Name Product"
                  :placeholder="$t('SearchByName')"
                  v-model="Filter_name"
                ></b-form-input>
              </b-form-group>
            </b-col>

            <!-- Category  -->
            <b-col md="12">
              <b-form-group :label="$t('Categorie')">
                <v-select
                  :reduce="label => label.value"
                  :placeholder="$t('Choose_Category')"
                  v-model="Filter_category"
                  :options="categories.map(categories => ({label: categories.name, value: categories.id}))"
                />
              </b-form-group>
            </b-col>

            <!-- Brand  -->
            <b-col md="12">
              <b-form-group :label="$t('Brand')">
                <v-select
                  :reduce="label => label.value"
                  :placeholder="$t('Choose_Brand')"
                  v-model="Filter_brand"
                  :options="brands.map(brands => ({label: brands.name, value: brands.id}))"
                />
              </b-form-group>
            </b-col>

            <b-col md="6" sm="12">
              <b-button
                @click="Get_Products(serverParams.page)"
                variant="primary m-1"
                size="sm"
                block
              >
                <i class="i-Filter-2"></i>
                {{ $t("Filter") }}
              </b-button>
            </b-col>

            <b-col md="6" sm="12">
              <b-button @click="Reset_Filter()" variant="danger m-1" size="sm" block>
                <i class="i-Power-2"></i>
                {{ $t("Reset") }}
              </b-button>
            </b-col>
          </b-row>
        </div>
      </b-sidebar>
    </div>
  </div>
</template>


<script>
import { mapActions, mapGetters } from "vuex";
import NProgress from "nprogress";
import jsPDF from "jspdf";
import "jspdf-autotable";

export default {
  metaInfo: {
    title: "Products"
  },

  data() {
    return {
      serverParams: {
        sort: {
          field: "id",
          type: "desc"
        },
        page: 1,
        perPage: 10
      },
      selectedIds: [],
      ImportProcessing:false,
      data: new FormData(),
      search: "",
      totalRows: "",
      rows: [{
        children: [],
      },],
      warehouse_id: "",
      isLoading: true,
      spinner: false,
      limit: "10",
      Filter_brand: "",
      Filter_code: "",
      Filter_name: "",
      Filter_category: "",
      categories: [],
      brands: [],
      products: {},
      warehouses: []
    };
  },

  computed: {
    ...mapGetters(["currentUserPermissions"]),
    columns() {
      return [
        {
          label: this.$t("image"),
          field: "image",
          type: "image",
          html: true,
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Name_product"),
          field: "name",
          html: true,
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Code"),
          field: "code",
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Categorie"),
          field: "category",
          tdClass: "text-left",
          thClass: "text-left"
        },
       
        {
          label: this.$t("Cost"),
          field: "cost",
          html: true,
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Price"),
          field: "price",
          html: true,
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Profit"),
          field: "benefice",
          html: true,
          tdClass: "text-left",
          thClass: "text-left"
        },
        {
          label: this.$t("Quantity"),
          field: "quantity",
          tdClass: "text-left",
          headerField: this.sumTotalQte,
          thClass: "text-left"
        },
        {
          label: this.$t("Total_Cost"),
          field: "total_cost",
          tdClass: "text-left",
          headerField: this.sumTotalCost,
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Total_Prix"),
          field: "total_amount",
          tdClass: "text-left",
          headerField: this.sumTotalAmount,
          thClass: "text-left",
          sortable: false
        },
        {
          label: this.$t("Total_Profit"),
          field: "total_profit",
          tdClass: "text-left",
          headerField: this.sumTotalProfit,
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
   

    //-------------------------------------- Products PDF ------------------------------\\
    Product_PDF() {
    var self = this;

        let pdf = new jsPDF("p", "pt");
        let columns = [
          { title: "name", dataKey: "name" },
          { title: "code", dataKey: "code" },
          { title: "category", dataKey: "category" },
          { title: "cost", dataKey: "cost" },
          { title: "price", dataKey: "price" },
          { title: "benefice", dataKey: "benefice" },
          { title: "quantity", dataKey: "quantity" },
          { title: "total_cost", dataKey: "total_cost" },
          { title: "total_amount", dataKey: "total_amount" },
          { title: "total_profit", dataKey: "total_profit" }
        ];

       // Create a copy of self.reports for PDF generation
       let products_pdf = JSON.parse(JSON.stringify(self.products));

      // Replace <br /> with newline character '\n' in the 'name' property of each item
      products_pdf.forEach(item => {
        item.name = item.name.replace(/<br\s*\/?>/gi, '\n');
        item.cost = item.cost.replace(/<br\s*\/?>/gi, '\n');
        item.price = item.price.replace(/<br\s*\/?>/gi, '\n');
      });

      pdf.autoTable(columns, products_pdf);
      pdf.text("Product List", 40, 25);
      pdf.save("Product_List.pdf");
    },

    //---------------------- Event Select Warehouse ------------------------------\\
    Selected_Warehouse(value) {
      if (value === null) {
        this.warehouse_id = "";
      }
      this.Get_Products(1);
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

    sumTotalCost(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) {
        console.error('Invalid input for Total Amount');
        return 0;
      }
      let sum_cost = 0;

      for (let i = 0; i < rowObj.children.length; i++) {
        sum_cost += rowObj.children[i].total_cost;
      }
      return sum_cost + ' dh';
    },

    sumTotalAmount(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) {
        console.error('Invalid input for Total Amount');
        return 0;
      }
      let sum_amount = 0;

      for (let i = 0; i < rowObj.children.length; i++) {
        sum_amount += rowObj.children[i].total_amount;
      }
      return sum_amount + ' dh';
    },

    sumTotalProfit(rowObj) {
      if (!rowObj || !rowObj.children || !Array.isArray(rowObj.children)) {
        console.error('Invalid input for Total Amount');
        return 0;
      }
      let sum = 0;
      for (let i = 0; i < rowObj.children.length; i++) {
          console.log(parseFloat(rowObj.children[i].total_profit));
          sum += parseFloat(rowObj.children[i].total_profit);
      }
      return sum + ' dh';
    },
    
    //------ Toast
    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true
      });
    },

    //----Update Params Table
    updateParams(newProps) {
      this.serverParams = Object.assign({}, this.serverParams, newProps);
    },

    //---- Event Page Change
    onPageChange({ currentPage }) {
      if (this.serverParams.page !== currentPage) {
        this.updateParams({ page: currentPage });
        this.Get_Products(currentPage);
      }
    },

    //---- Event Per Page Change
    onPerPageChange({ currentPerPage }) {
      if (this.limit !== currentPerPage) {
        this.limit = currentPerPage;
        this.updateParams({ page: 1, perPage: currentPerPage });
        this.Get_Products(1);
      }
    },

    //---- Event Select Rows
    selectionChanged({ selectedRows }) {
      this.selectedIds = [];
      selectedRows.forEach((row, index) => {
        this.selectedIds.push(row.id);
      });
    },

    //---- Event Sort Change
    onSortChange(params) {
      let field = "";
      if (params[0].field == "brand") {
        field = "brand_id";
      } else if (params[0].field == "category") {
        field = "category_id";
      } else {
        field = params[0].field;
      }
      this.updateParams({
        sort: {
          type: params[0].type,
          field: field
        }
      });
      this.Get_Products(this.serverParams.page);
    },

    //---- Event Search
    onSearch(value) {
      this.search = value.searchTerm;
      this.Get_Products(this.serverParams.page);
    },

    //------ Reset Filter
    Reset_Filter() {
      this.search = "";
      this.Filter_brand = "";
      this.Filter_code = "";
      this.Filter_name = "";
      this.Filter_category = "";
      this.Get_Products(this.serverParams.page);
    },

    // Simply replaces null values with strings=''
    setToStrings() {
      if (this.Filter_category === null) {
        this.Filter_category = "";
      } else if (this.Filter_brand === null) {
        this.Filter_brand = "";
      }
    },

    //----------------------------------- Get All Products ------------------------------\\
    Get_Products(page) {
      // Start the progress bar.
      NProgress.start();
      NProgress.set(0.1);
      this.setToStrings();
      axios
        .get(
          "products?page=" +
            page +
            "&code=" +
            this.Filter_code +
            "&name=" +
            this.Filter_name +
            "&category_id=" +
            this.Filter_category +
            "&brand_id=" +
            this.Filter_brand +
            "&SortField=" +
            this.serverParams.sort.field +
            "&SortType=" +
            this.serverParams.sort.type +
            "&search=" +
            this.search +
            "&limit=" +
            this.limit +
            "&warehouse_id=" +
            this.warehouse_id
        )
        .then(response => {
          this.products = response.data.products;
          this.rows[0].children = this.products;
          this.warehouses = response.data.warehouses;
          this.categories = response.data.categories;
          this.brands = response.data.brands;
          this.totalRows = response.data.totalRows;

          // Complete the animation of theprogress bar.
          NProgress.done();
          this.isLoading = false;
        })
        .catch(response => {
          // Complete the animation of theprogress bar.
          NProgress.done();
          setTimeout(() => {
            this.isLoading = false;
          }, 500);
        });
    },

    //----------------------------------- Remove Product ------------------------------\\
    Remove_Product(id) {
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
          // Start the progress bar.
          NProgress.start();
          NProgress.set(0.1);
          axios
            .delete("products/" + id)
            .then(() => {
              this.$swal(
                this.$t("Delete.Deleted"),
                this.$t("Delete.ProductDeleted"),
                "success"
              );

              Fire.$emit("Delete_Product");
            })
            .catch(() => {
              // Complete the animation of theprogress bar.
              setTimeout(() => NProgress.done(), 500);
              this.$swal(
                this.$t("Delete.Failed"),
                this.$t("Delete.AlreadyLinked"),
                "warning"
              );
            });
        }
      });
    },

    //---- Delete products by selection
    delete_by_selected() {
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
          // Start the progress bar.
          NProgress.start();
          NProgress.set(0.1);
          axios
            .post("products/delete/by_selection", {
              selectedIds: this.selectedIds
            })
            .then(() => {
              this.$swal(
                this.$t("Delete.Deleted"),
                this.$t("Delete.ProductDeleted"),
                "success"
              );

              Fire.$emit("Delete_Product");
            })
            .catch(() => {
              // Complete the animation of theprogress bar.
              setTimeout(() => NProgress.done(), 500);
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

  //-----------------------------Created function-------------------

  created: function() {
    this.Get_Products(1);

    Fire.$on("Delete_Product", () => {
      this.Get_Products(this.serverParams.page);
      // Complete the animation of theprogress bar.
      setTimeout(() => NProgress.done(), 500);
    });
  }
};
</script>
