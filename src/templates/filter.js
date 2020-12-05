Vue.component('agg', {
 template:`<li v-show="show" ><label><input type="checkbox"  @change="filter" v-model="$root.selected" v-bind:value="val"> {{agg.key}} ({{agg.doc_count}})</label></li>`,
   props:{
     agg:Object,
     name:String,
     index:Number
   },
   computed:{
     show:function(){
       if(this.index < 5 || this.$root.open.includes(this.name)){
          if(this.agg.doc_count > 0){
         return true;
       }
       }
     },
     val:function(){
        if(this.name == 'Date' ){
          return  { "range": { "P:29.datField": { "gte": Number(this.agg.from+'.0000000'), "lte": Number(this.agg.to+'.0000000')}}};
        }else{
       return {value:this.agg.key, key:this.name};
     }
     },
    selected:{

      }
   },
   data(){
     return {
   hoi:'hoi'
     }
   },
   methods:{
     filter:function(e){



       var root = this.$root;
          root.loading = true;
           root.from = 0;
       var params = {
           action: 'query',
           meta: 'smws',
           format: 'json',
           smfilter: JSON.stringify(root.selected),
           smtitle: root.titleID,
           smexerpt: root.exerpt,
           smaggs: Object.keys(root.aggs).join(),
           smclass:root.main,
          smterm:root.term,
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

}
   }
 });
