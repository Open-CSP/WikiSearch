Vue.component('hit', {
  template:'<div class="wssearch--hit" ><div class="wssearch--hit--info"><span>Page:{{hit._source.subject.title}}</span>•<span>{{date}}</span></div><span v-for="(hitID, key) in $root.hitIDs"><a v-if="Object.keys($root.hitIDs)[0] == key" v-bind:href="href" class="wssearch--hit--link">{{hit._source["P:" + key ][hitID][0]}}</a><span v-else>{{hit._source["P:" + key ][hitID][0]}}</span></span><div class="wssearch--hit--body" v-html="exerpt"></div></div>',
  props:{
    hit:Object
  },
    computed:{
      date:function(){
        var unsplit = this.hit._source["P:29"].dat_raw[0];
        var rawz = unsplit.split("/");
        var ndate = rawz[3] + ' ' + this.$root.monthnames[rawz[2] - 1] + ', ' + rawz[1];
        return ndate;

      },
    exerpt:function(){
      if(this.hit.highlight){
          if(this.hit.highlight['text_raw']){
            return this.hit.highlight['text_raw'][0];
          }
        }
    },
    href:function(){
    if(this.hit._source.subject.namespacename){
       return "/" + this.hit._source.subject.namespacename + ":" + this.hit._source.subject.title;
     }else{
       return "/" + this.hit._source.subject.title;
     }
    }
  },
  data(){
    return {
      hoi:'hoi'
    }
  },
  methods:{

  }
});


Vue.component('agg', {
  template:`<li v-show="show" ><label><input type="checkbox" v-bind:id="name + agg.key" @change="filter" v-model="$root.selected" v-bind:value="val"> {{title}} ({{agg.doc_count}})</label></li>`,
  props:{
    agg:Object,
    name:String,
    index:Number
  },
  computed:{
    title:function(){
      if(this.agg.name){
        return this.agg.name;
      }else{
        return this.agg.key;
      }
    },
    show:function(){
      if(this.index < 5 || this.$root.open.includes(this.name)){
        if(this.agg.doc_count > 0){
          return true;
        }
      }
    },
    val:function(){
      if(this.name == 'Date' ){
        return  { "value" : this.agg.key , "key": this.name,  "range": { "P:29.datField": { "gte": Number(this.agg.from+'.0000000'), "lte": Number(this.agg.to+'.0000000')}}};
      }else{
        return {value:this.agg.key, key:this.name};
      }
    }
  },
  data(){
    return {
      hoi:'hoi'
    }
  },
  methods:{
    filter:function(e){
      this.$root.api(0, this.$root.term);
    }
  }
});

// dev mode, replace with "var app ="
window.app = new Vue({ el: "#app",
  data:{
    total: vueinitdata.total,
    hits: vueinitdata.hits,
    aggs: vueinitdata.aggs,
    size:10,
    from: 0,
    selected: vueinitdata.selected ,
    open: [],
    monthnames: ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Nov","Dec"],
    rangeFrom: 0,
    rangeTo: 80,
    dropdown: [],
    term: vueinitdata.term ,
    loading: false ,
    dates:vueinitdata.dates,
    filterIDs:vueinitdata.filterIDs,
    hitIDs:vueinitdata.hitIDs
    },

  methods:{
    api:function(from, term){

      var root = this;
      root.loading = true;
      root.from = from;
      root.term = term


      history.pushState({page: 1}, "title 1", this.urlstring() );

      var params = {
        action: 'query',
        meta: 'WSSearch',
        format: 'json',
        smfilter: JSON.stringify(root.selected),
        smterm:root.term,
        smpage:mw.config.values.wgPageName,
        smdates:JSON.stringify(root.dates)
      },
      api = new mw.Api();

      api.post(  params ).done( function ( data ) {

        console.log( data );
        root.total = data.result.total;
        root.hits = JSON.parse(data.result.hits);
        root.aggs = JSON.parse(data.result.aggs);
        root.loading = false;

      })
    },
    urlstring:function(){

      var url = "?";

      if(this.from > 0){
        url += "&offset="+ this.from;
      }

      if(this.term){
        url += "&term="+ this.term;
      }

     if(this.selected.length){
       url += "&filters=";
       var filtersarray = [];
      this.selected.forEach(function(item, i){
        if(item.range){
          filtersarray.push("range_"+item.value+"_"+item.key+"-"+item.range["P:29.datField"].gte+"_"+item.range["P:29.datField"].lte);
        }else{
          filtersarray.push(""+item.key+"-"+item.value+"");
        }
      });
      url += filtersarray.join("~");
    }
      return encodeURI(url);
    },
    clearfilters:function(e){
      this.selected = [];
      this.api(0, this.term);
    },
    more:function(prop){
      var index = this.open.indexOf(prop);
      if (index > -1) {
        this.open.splice(index, 1);
      }else{
        this.open.push(prop);
      }
    },
    search:function(e){
      this.api(0, e.target.value);
    },
    activepage:function(pager){
      if(pager == (this.from / this.size) + 1){
        return 'active';
      }
    },
    next:function(e){
      var root = this;
      root.loading = true;
      if(e.target.innerText.trim() == '<'){
        this.from = this.from - this.size;
      }else if(e.target.innerText.trim() == '>'){
        this.from = this.from + this.size;
      }else{
        this.from = Math.ceil(this.size * (e.target.innerText - 1));
      }
      this.api(this.from, root.term);
    }
  },
  computed:{
    mainloading:function(){
      if(this.loading){
        return 'wssearch--main wssearch--main__loading';
      }else{
        return 'wssearch--main';
      }

    },
    sort:function(){
      this.aggs.Date.buckets = this.aggs.Date.buckets.filter(function(el){
        if(el.doc_count > 0){
          return el;
        }
      }).reverse();
    },
    pagers:function(e){
      if(this.total >= this.size){
        if(this.from == 0){
          var pages = [];
        }else{
          var pages = ['<'];
        }
        var i;
        var step = Math.ceil(this.total / this.size);
        for (i = 0; i < step; i++) {
          pages.push(i + 1)
        }
        if(this.from + this.size >=  this.total - this.size){
        }else{
          pages.push('>');
        }
        return pages;
      }
    }
  }
});
