Vue.component('hit', {
 template:`<div><a v-bind:href="href">{{title}}</a><br><small>{{exerpt}}</small></div>`,
   props:{
     hit:Object
   },
   computed:{
     title:function(){
       if(this.hit._source['P:'+ this.$root.titleID]){
          if(this.hit._source['P:'+ this.$root.titleID].txtField){
             return this.hit._source['P:'+ this.$root.titleID].txtField[0]
           }else if(this.hit._source['P:'+ this.$root.titleID].wpgField) {
             return this.hit._source['P:'+ this.$root.titleID].wpgField[0]
           }else{
              return 'set exerpt property'
           }
         }else{
           return '?';
         }
     },
     exerpt:function(){
 if(this.hit._source['P:'+ this.$root.exerptID]){
    if(this.hit._source['P:'+ this.$root.exerptID].txtField){
       return this.hit._source['P:'+ this.$root.exerptID].txtField[0]
     }else if(this.hit._source['P:'+ this.$root.exerptID].wpgField) {
       return this.hit._source['P:'+ this.$root.exerptID].wpgField[0]
     }else{
        return 'set exerpt property'
     }
   }else{
     return '?';
   }
     },
     href:function(){
       return this.hit._source.subject.title
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
