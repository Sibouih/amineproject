<template>
  <div class="main-content">
    <breadcumb :page="$t('Search_a_product')" :folder="$t('Products')" />
    <div
      v-if="isLoading"
      class="loading_page spinner spinner-primary mr-3"
    ></div>

    <b-row v-if="!isLoading">
      <!-- Search Input -->
      <b-col md="12" class="mb-4">
        <b-card class="search-card">
          <h5 class="search-title mb-3">{{ $t("Search_a_product") }}</h5>
          <div id="autocomplete" class="autocomplete">
            <input
              :placeholder="$t('Search_a_product')"
              @input="(e) => (search_input = e.target.value)"
              @keyup="search(search_input)"
              @focus="handleFocus"
              @blur="handleBlur"
              ref="product_autocomplete"
              class="autocomplete-input"
            />
            <ul class="autocomplete-result-list" v-show="focused">
              <li
                class="autocomplete-result"
                v-for="product_fil in product_filter"
                @mousedown="SearchProduct(product_fil)"
              >
                {{ getResultValue(product_fil) }}
              </li>
            </ul>
          </div>
        </b-card>
      </b-col>

      <!-- Product Details -->
      <b-col md="12" v-if="product.code">
        <b-card class="product-details-card">
          <b-row>
            <!-- Barcode Section -->
            <b-col md="12" class="mb-5" v-if="product.type != 'is_variant'">
              <barcode
                class="barcode"
                :format="product.Type_barcode"
                :value="product.code"
                textmargin="0"
                fontoptions="bold"
              ></barcode>
            </b-col>

            <!-- Details Section -->
            <b-col md="8">
              <table class="table table-hover table-bordered table-md">
                <tbody>
                  <tr>
                    <td>{{ $t("ProductName") }}</td>
                    <th>{{ product.name }}</th>
                  </tr>
                  <tr>
                    <td>{{ $t("Categorie") }}</td>
                    <th>{{ product.category }}</th>
                  </tr>
                  <tr>
                    <td>{{ $t("Brand") }}</td>
                    <th>{{ product.brand }}</th>
                  </tr>
                  <tr>
                    <td>{{ $t("Cost") }}</td>
                    <th>{{ formatNumber(product.cost, 2) }} dh</th>
                  </tr>
                  <tr>
                    <td>{{ $t("Price") }}</td>
                    <th>{{ formatNumber(product.Net_price, 2) }} dh</th>
                  </tr>
                  <tr v-if="product.stockage">
                    <td>{{ $t("Storage") }}</td>
                    <th>{{ product.stockage }}</th>
                  </tr>
                  <tr v-if="product.battery">
                    <td>{{ $t("Battery") }}</td>
                    <th>{{ product.battery }}%</th>
                  </tr>
                  <tr>
                    <td>{{ $t("QuantityStock") }}</td>
                    <td>
                      <span class="badge badge-outline-primary">{{
                        product.qte
                      }}</span>
                    </td>
                  </tr>
                  <tr v-if="product.type != 'is_service'">
                    <td>{{ $t("StockAlert") }}</td>
                    <th>
                      <span class="badge badge-outline-warning">{{
                        formatNumber(product.stock_alert, 2)
                      }}</span>
                    </th>
                  </tr>
                </tbody>
              </table>
            </b-col>

            <!-- Image Section -->
            <b-col
              md="4"
              class="mb-30"
              v-if="product.images && product.images.length"
            >
              <div class="carousel_wrap">
                <b-carousel
                  id="carousel-1"
                  :interval="2000"
                  controls
                  background="#ababab"
                  img-width="1024"
                  img-height="480"
                >
                  <b-carousel-slide
                    v-for="(image, index) in product.images"
                    :key="index"
                    :img-src="'/images/products/' + image"
                  ></b-carousel-slide>
                </b-carousel>
              </div>
            </b-col>
          </b-row>
        </b-card>
      </b-col>
    </b-row>
  </div>
</template>

<script>
import VueBarcode from "vue-barcode";
import NProgress from "nprogress";

export default {
  metaInfo: {
    title: "Search Product",
  },

  components: {
    barcode: VueBarcode,
  },

  data() {
    return {
      isLoading: true,
      focused: false,
      timer: null,
      search_input: "",
      product_filter: [],
      warehouse_id: 1,
      products: [],
      product: {
        id: "",
        name: "",
        code: "",
        Type_barcode: "",
        category: "",
        brand: "",
        barcode: "",
        Net_price: 0,
        cost: 0,
        stockage: "",
        battery: "",
        qte_sale: 0,
      },
    };
  },

  methods: {
    formatNumber(number, dec) {
      if (number === null || number === undefined) {
        number = 0;
      }

      const value = (
        typeof number === "string" ? number : number.toString()
      ).split(".");

      if (dec <= 0) return value[0];

      let formattedNumber = value[0] || "";
      const decimalPart = value[1] || "";

      if (decimalPart.length < dec) {
        formattedNumber +=
          "." + decimalPart + "0".repeat(dec - decimalPart.length);
      } else {
        formattedNumber += "." + decimalPart.substr(0, dec);
      }
      return formattedNumber;
    },

    handleFocus() {
      this.focused = true;
    },

    handleBlur() {
      this.focused = false;
    },

    search() {
      if (this.timer) {
        clearTimeout(this.timer);
        this.timer = null;
      }

      if (this.search_input.length < 2) {
        return (this.product_filter = []);
      }

      this.timer = setTimeout(() => {
        const product_filter = this.products.filter(
          (product) =>
            product.code
              .toLowerCase()
              .includes(this.search_input.toLowerCase()) ||
            product.barcode
              .toLowerCase()
              .includes(this.search_input.toLowerCase())
        );

        if (product_filter.length === 1) {
          this.SearchProduct(product_filter[0]);
        } else {
          this.product_filter = this.products.filter((product) => {
            return (
              (product.name?.toLowerCase() || "").includes(
                this.search_input.toLowerCase()
              ) ||
              (product.code?.toLowerCase() || "").includes(
                this.search_input.toLowerCase()
              ) ||
              (product.barcode?.toLowerCase() || "").includes(
                this.search_input.toLowerCase()
              )
            );
          });
        }
      }, 800);
    },

    getResultValue(result) {
      return result.code + " " + "(" + result.name + ")";
    },

    SearchProduct(result) {
      this.product = {
        id: result.id,
        name: result.name,
        code: result.code,
        Type_barcode: result.Type_barcode,
        barcode: result.barcode,
        Net_price: result.Net_price,
        qte: result.qte,
        cost: result.cost,
        stockage: result.stockage,
        battery: result.battery,
        qte_sale: result.qte_sale,
        category: result.category,
        brand: result.brand,
      };

      this.search_input = "";
      this.$refs.product_autocomplete.value = "";
      this.product_filter = [];
    },

    Get_Products_By_Warehouse(id) {
      NProgress.start();
      NProgress.set(0.1);

      axios
        .get(`get_Products_by_warehouse/${id}`)
        .then((response) => {
          this.products = response.data;
          console.log("this.products", this.products);
          NProgress.done();
        })
        .catch((error) => {
          NProgress.done();
        });
    },

    Get_Elements() {
      axios
        .get("barcode_create_page")
        .then((response) => {
          if (response.data.warehouses && response.data.warehouses.length > 0) {
            this.warehouse_id = response.data.warehouses[0].id;
            this.Get_Products_By_Warehouse(this.warehouse_id);
          }
          this.isLoading = false;
        })
        .catch((error) => {
          this.isLoading = false;
        });
    },

    makeToast(variant, msg, title) {
      this.$root.$bvToast.toast(msg, {
        title: title,
        variant: variant,
        solid: true,
      });
    },
  },

  created() {
    this.Get_Elements();
  },
};
</script>

<style scoped>
.search-card {
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.search-title {
  color: #2c3e50;
  font-weight: 600;
}

.autocomplete {
  position: relative;
}

.autocomplete-input {
  width: 100%;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 1rem;
  transition: border-color 0.2s ease;
}

.autocomplete-input:focus {
  border-color: #4299e1;
  outline: none;
  box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

.autocomplete-result:hover {
  background-color: #f7fafc;
}

.product-details-card {
  background: #fff;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.barcode-section {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.5rem;
  border-right: 1px solid #e2e8f0;
}

.barcode-container {
  background: #f8fafc;
  padding: 1rem;
  border-radius: 6px;
}

.table-details {
  margin: 0;
}

.table-details tr {
  border-bottom: 1px solid #f0f0f0;
}

.table-details tr:last-child {
  border-bottom: none;
}

.label-column {
  width: 35%;
  color: #4a5568;
  padding: 1rem;
}

.value-column {
  color: #2d3748;
  font-weight: 500;
  padding: 1rem;
}
</style>
