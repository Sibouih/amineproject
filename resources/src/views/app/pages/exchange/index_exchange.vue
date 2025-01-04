<template>
    <div class="main-content">
      <breadcumb :page="$t('ExchangeList')" :folder="$t('Exchanges')"/>
      <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
      <div v-else>
        <vue-good-table
          mode="remote"
          :columns="columns"
          :totalRows="totalRows"
          :rows="exchanges"
          @on-page-change="onPageChange"
          @on-per-page-change="onPerPageChange"
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
          styleClass="table-hover tableOne vgt-table">
          <div slot="table-actions" class="mt-2 mb-3">
            <router-link
              v-if="currentUserPermissions && currentUserPermissions.includes('exchange_add')"
              class="btn-sm btn-primary btn-icon ripple mr-1"
              to="/app/exchange/store">
              <span class="ul-btn__icon">
                <i class="i-Add"></i>
              </span>
              <span class="ul-btn__text">{{$t('Create')}}</span>
            </router-link>
          </div>
  
          <template slot="table-row" slot-scope="props">
            <span v-if="props.column.field == 'actions'">
              <router-link
                v-if="currentUserPermissions && currentUserPermissions.includes('exchange_details')"
                :to="{ name: 'detail_exchange', params: { id: props.row.id} }"
                class="btn btn-icon btn-sm"
                v-b-tooltip.hover
                :title="$t('Details')">
                <i class="i-Eye text-25 text-info"></i>
              </router-link>
            </span>
          </template>
        </vue-good-table>
      </div>
    </div>
  </template>
  
  <!-- create_exchange.vue template -->
  <template>
    <div class="main-content">
      <breadcumb :page="$t('AddExchange')" :folder="$t('Exchanges')"/>
      <div v-if="isLoading" class="loading_page spinner spinner-primary mr-3"></div>
  
      <validation-observer ref="create_exchange" v-if="!isLoading">
        <b-form @submit.prevent="Submit_Exchange">
          <b-row>
            <b-col lg="12" md="12" sm="12">
              <b-card>
                <b-row>
                  <!-- Date -->
                  <b-col lg="4" md="4" sm="12" class="mb-3">
                    <validation-provider
                      name="date"
                      :rules="{ required: true}"
                      v-slot="validationContext"
                    >
                      <b-form-group :label="$t('date') + ' ' + '*'">
                        <b-form-input
                          :state="getValidationState(validationContext)"
                          aria-describedby="date-feedback"
                          type="date"
                          v-model="exchange.date"
                        ></b-form-input>
                        <b-form-invalid-feedback
                          id="date-feedback"
                        >{{ validationContext.errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>
  
                  <!-- Customer -->
                  <b-col lg="4" md="4" sm="12" class="mb-3">
                    <validation-provider name="Customer" :rules="{ required: true}">
                      <b-form-group slot-scope="{ valid, errors }" :label="$t('Customer') + ' ' + '*'">
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          v-model="exchange.customer_id"
                          :reduce="label => label.value"
                          :placeholder="$t('Choose_Customer')"
                          :options="customers.map(customers => ({label: customers.name, value: customers.id}))"
                        />
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>
  
                  <!-- Supplier -->
                  <b-col lg="4" md="4" sm="12" class="mb-3">
                    <validation-provider name="Supplier" :rules="{ required: true}">
                      <b-form-group slot-scope="{ valid, errors }" :label="$t('Supplier') + ' ' + '*'">
                        <v-select
                          :class="{'is-invalid': !!errors.length}"
                          :state="errors[0] ? false : (valid ? true : null)"
                          v-model="exchange.supplier_id"
                          :reduce="label => label.value"
                          :placeholder="$t('Choose_Supplier')"
                          :options="suppliers.map(suppliers => ({label: suppliers.name, value: suppliers.id}))"
                        />
                        <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                      </b-form-group>
                    </validation-provider>
                  </b-col>                    
                  <!-- Products Search -->
                  <b-col md="12" class="mb-5">
                    <h6>{{$t('ProductName')}}</h6>
                    <div id="autocomplete" class="autocomplete">
                      <input 
                        :placeholder="$t('Scan_Search_Product_by_Code_Name')"
                        @input='e => search_input = e.target.value' 
                        @keyup="search(search_input)"
                        @focus="handleFocus"
                        @blur="handleBlur"
                        class="autocomplete-input" />
                        
                      <ul class="autocomplete-result-list" v-show="focused">
                        <li class="autocomplete-result" v-for="product_fil in product_filter"
                          @mousedown="SearchProduct(product_fil)">
                          <span>{{getResultValue(product_fil)}}</span>
                        </li>
                      </ul>
                    </div>
                  </b-col>
                  <b-col md="12">
                  <h5>{{$t('Products_Receiving')}} *</h5>
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead class="bg-gray-300">
                        <tr>
                          <th scope="col">#</th>
                          <th scope="col">{{$t('ProductName')}}</th>
                          <th scope="col">{{$t('Net_Unit_Cost')}}</th>
                          <th scope="col">{{$t('Qty')}}</th>
                          <th scope="col">{{$t('Discount')}}</th>
                          <th scope="col">{{$t('Tax')}}</th>
                          <th scope="col">{{$t('SubTotal')}}</th>
                          <th scope="col" class="text-center">
                            <i class="i-Close-Window text-25 cursor-pointer"></i>
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="exchange_in.length <=0">
                          <td colspan="8">{{$t('NodataAvailable')}}</td>
                        </tr>
                        <tr v-for="(detail, index) in exchange_in" :key="index">
                          <td>{{++index}}</td>
                          <td>
                            <span>{{detail.code}}</span>
                            <br>
                            <span class="badge badge-success">{{detail.name}}</span>
                          </td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.Net_cost, 3)}}</td>
                          <td>
                            <div class="quantity">
                              <b-input-group>
                                <b-input-group-prepend>
                                  <span class="btn btn-primary btn-sm" @click="decrement_in(detail,index)">-</span>
                                </b-input-group-prepend>
                                <input class="form-control" v-model.number="detail.quantity" @keyup="Verified_Qty_in(detail,index)">
                                <b-input-group-append>
                                  <span class="btn btn-primary btn-sm" @click="increment_in(detail,index)">+</span>
                                </b-input-group-append>
                              </b-input-group>
                            </div>
                          </td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.DiscountNet * detail.quantity, 2)}}</td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.taxe * detail.quantity, 2)}}</td>
                          <td>{{currentUser.currency}} {{detail.subtotal.toFixed(2)}}</td>
                          <td>
                            <a @click="delete_Product_in(index)" class="btn btn-icon btn-sm" title="Delete">
                              <i class="i-Close-Window text-25 text-danger"></i>
                            </a>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </b-col>

                <!-- Products Being Exchanged Out (Sales) -->
                <b-col md="12" class="mt-4">
                  <h5>{{$t('Products_Giving')}} *</h5>
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead class="bg-gray-300">
                        <tr>
                          <th scope="col">#</th>
                          <th scope="col">{{$t('ProductName')}}</th>
                          <th scope="col">{{$t('Net_Unit_Price')}}</th>
                          <th scope="col">{{$t('Qty')}}</th>
                          <th scope="col">{{$t('Discount')}}</th>
                          <th scope="col">{{$t('Tax')}}</th>
                          <th scope="col">{{$t('SubTotal')}}</th>
                          <th scope="col" class="text-center">
                            <i class="i-Close-Window text-25 cursor-pointer"></i>
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-if="exchange_out.length <=0">
                          <td colspan="8">{{$t('NodataAvailable')}}</td>
                        </tr>
                        <tr v-for="(detail, index) in exchange_out" :key="index">
                          <td>{{++index}}</td>
                          <td>
                            <span>{{detail.code}}</span>
                            <br>
                            <span class="badge badge-success">{{detail.name}}</span>
                          </td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.Net_price, 3)}}</td>
                          <td>
                            <div class="quantity">
                              <b-input-group>
                                <b-input-group-prepend>
                                  <span class="btn btn-primary btn-sm" @click="decrement_out(detail,index)">-</span>
                                </b-input-group-prepend>
                                <input class="form-control" v-model.number="detail.quantity" @keyup="Verified_Qty_out(detail,index)">
                                <b-input-group-append>
                                  <span class="btn btn-primary btn-sm" @click="increment_out(detail,index)">+</span>
                                </b-input-group-append>
                              </b-input-group>
                            </div>
                          </td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.DiscountNet * detail.quantity, 2)}}</td>
                          <td>{{currentUser.currency}} {{formatNumber(detail.taxe * detail.quantity, 2)}}</td>
                          <td>{{currentUser.currency}} {{detail.subtotal.toFixed(2)}}</td>
                          <td>
                            <a @click="delete_Product_out(index)" class="btn btn-icon btn-sm" title="Delete">
                              <i class="i-Close-Window text-25 text-danger"></i>
                            </a>
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </b-col>

                <div class="offset-md-9 col-md-3 mt-4">
                  <table class="table table-striped table-sm">
                    <tbody>
                      <tr>
                        <td class="bold">{{$t('OrderTax')}}</td>
                        <td>
                          <span>{{currentUser.currency}} {{exchange.TaxNet.toFixed(2)}} ({{formatNumber(exchange.tax_rate, 2)}} %)</span>
                        </td>
                      </tr>
                      <tr>
                        <td class="bold">{{$t('Discount')}}</td>
                        <td>{{currentUser.currency}} {{exchange.discount.toFixed(2)}}</td>
                      </tr>
                      <tr>
                        <td class="bold">{{$t('Shipping')}}</td>
                        <td>{{currentUser.currency}} {{exchange.shipping.toFixed(2)}}</td>
                      </tr>
                      <tr>
                        <td>
                          <span class="font-weight-bold">{{$t('Total')}}</span>
                        </td>
                        <td>
                          <span class="font-weight-bold">{{currentUser.currency}} {{GrandTotal.toFixed(2)}}</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <!-- Order Tax  -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="Order Tax" :rules="{ regex: /^\d*\.?\d*$/}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('OrderTax')">
                      <div class="input-group">
                        <input :state="errors[0] ? false : (valid ? true : null)" 
                          v-model.number="exchange.tax_rate" @keyup="keyup_OrderTax()" 
                          class="form-control" :placeholder="$t('OrderTax')">
                        <div class="input-group-append">
                          <span class="input-group-text">%</span>
                        </div>
                      </div>
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Discount -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="Discount" :rules="{ regex: /^\d*\.?\d*$/}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('Discount')">
                      <div class="input-group">
                        <input :state="errors[0] ? false : (valid ? true : null)"
                          v-model.number="exchange.discount" @keyup="keyup_Discount()"
                          class="form-control" :placeholder="$t('Discount')">
                        <div class="input-group-append">
                          <span class="input-group-text">{{currentUser.currency}}</span>
                        </div>
                      </div>
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Shipping  -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="Shipping" :rules="{ regex: /^\d*\.?\d*$/}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('Shipping')">
                      <div class="input-group">
                        <input :state="errors[0] ? false : (valid ? true : null)"
                          v-model.number="exchange.shipping" @keyup="keyup_Shipping()"
                          class="form-control" :placeholder="$t('Shipping')">
                        <div class="input-group-append">
                          <span class="input-group-text">{{currentUser.currency}}</span>
                        </div>
                      </div>
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <!-- Status -->
                <b-col lg="4" md="4" sm="12" class="mb-3">
                  <validation-provider name="Status" :rules="{ required: true}">
                    <b-form-group slot-scope="{ valid, errors }" :label="$t('Status') + ' ' + '*'">
                      <v-select
                        :class="{'is-invalid': !!errors.length}"
                        :state="errors[0] ? false : (valid ? true : null)"
                        v-model="exchange.status"
                        :reduce="label => label.value"
                        :placeholder="$t('Choose_Status')"
                        :options="[
                          {label: 'completed', value: 'completed'},
                          {label: 'pending', value: 'pending'},
                        ]">
                      </v-select>
                      <b-form-invalid-feedback>{{ errors[0] }}</b-form-invalid-feedback>
                    </b-form-group>
                  </validation-provider>
                </b-col>

                <b-col md="12">
                  <b-form-group :label="$t('Note')">
                    <textarea v-model="exchange.notes" rows="4" class="form-control" :placeholder="$t('Afewwords')"></textarea>
                  </b-form-group>
                </b-col>

                <b-col md="12">
                  <b-form-group>
                    <b-button variant="primary" @click="Submit_Exchange" :disabled="SubmitProcessing">
                      <i class="i-Yes me-2 font-weight-bold"></i> {{$t('submit')}}
                    </b-button>
                    <div v-once class="typo__p" v-if="SubmitProcessing">
                      <div class="spinner sm spinner-primary mt-3"></div>
                    </div>
                  </b-form-group>
                </b-col>

              </b-row>
            </b-card>
          </b-col>
        </b-row>
      </b-form>
    </validation-observer>
  </div>
</template>

export default {
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
        totalRows: "",
        exchanges: [],
        limit: "10",
        search: "",
        status: "",
      };
    },
    methods: {
      //---- update Params
      updateParams(newProps) {
        this.serverParams = Object.assign({}, this.serverParams, newProps);
      },
  
      //---- Event Page Change
      onPageChange({ currentPage }) {
        if (this.serverParams.page !== currentPage) {
          this.updateParams({ page: currentPage });
          this.Get_Exchanges(currentPage);
        }
      },
  
      //---- Event Per Page Change
      onPerPageChange({ currentPerPage }) {
        if (this.limit !== currentPerPage) {
          this.limit = currentPerPage;
          this.updateParams({ page: 1, perPage: currentPerPage });
          this.Get_Exchanges(1);
        }
      },
  
      //---- Get All Exchanges
      Get_Exchanges(page) {
        axios
          .get("/api/exchange?page=" + page + "&limit=" + this.limit)
          .then(response => {
            this.exchanges = response.data.exchanges;
            this.totalRows = response.data.totalRows;
            this.isLoading = false;
          })
          .catch(response => {
            setTimeout(() => {
              this.isLoading = false;
            }, 500);
          });
      }
    }, 
  
    //-----------------------------created function-------------------
    created: function() {
      this.Get_Exchanges(1);
    }
  };