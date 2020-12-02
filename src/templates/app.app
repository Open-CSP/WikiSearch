<div id="app">
  <input type="text" placeholder="search" @change="search" >  Found <b>{{total}}</b> results
  <div class="smws">


  <div class="smws--filter">


  <h2>Filters</h2>
  <div v-for="(ag, name, index) in aggs">
    <h3>{{name}}</h3>
    <ul>
      <agg v-for="agg in ag.buckets" v-bind:agg="agg"  v-bind:name="name"></agg>

    </ul>
  </div>
  </div>
  <div class="smws--hits">
  <hit v-for="hit in hits" v-bind:hit="hit" ></hit>
  <div  >
    <span v-for="pager in pagers" @click="nextz" v-bind:class="activepage(pager)">
      <b v-if="activepage(pager)">
        {{pager}}
      </b>
      <span v-else>
        {{pager}}
      </span>
    </span>


    </div>
  </div>

    </div>
</div>
