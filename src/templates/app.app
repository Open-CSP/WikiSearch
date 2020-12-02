<div id="app"><input type="text" placeholder="search" @change="search" >  Found <b>{{total}}</b> results
<h2>Filters</h2>
<div v-for="(ag, name, index) in aggs">
<h3>{{name}}</h3>
<ul>
 <agg v-for="agg in ag.buckets" v-bind:agg="agg"  v-bind:name="name"></agg>

</ul>
</div>
<hit v-for="hit in hits" v-bind:hit="hit" ></hit>
<div @click="nextz" >Next></div>
</div>
