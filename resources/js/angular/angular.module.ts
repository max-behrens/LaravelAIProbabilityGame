import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { UserComponent, UserService } from './user.component';

@NgModule({
  declarations: [],
  imports: [
    BrowserModule,
    UserComponent
  ],
  providers: [UserService],
  bootstrap: [UserComponent]
})
export class AppModule { }