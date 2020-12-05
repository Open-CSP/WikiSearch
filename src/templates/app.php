<?php echo`<div id="app">

  <div class="smws">


  <div class="smws--side">

 <input type="text" placeholder="search" @change="search" class="form-control">
  <h2>Filters</h2>
  <div class="smws--filter">{{sort}}
  <div v-for="(name, index) in Object.keys(aggs).sort()">
    <h3>{{name}}</h3>
    <ul>
      <agg v-for="(agg, i) in aggs[name].buckets" v-bind:agg="agg"  v-bind:index="i" v-bind:name="name"></agg>
      <li v-if="aggs[name].buckets.length > 5 && !open.includes(name)" @click="more(name)">More ><li>
    </ul>
  </div>
  </div>
  </div>
  <div class="smws--main">
    <div class="smws--results">
    Found <b>{{total}}</b> results<br>
    </div>
    <div class="smws--hits">
  <hit v-for="hit in hits" v-bind:hit="hit" ></hit>
  </div>
  <div  class="smws--pager">
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
</div>`;
